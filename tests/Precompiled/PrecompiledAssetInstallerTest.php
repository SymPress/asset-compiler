<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Tests\Precompiled;

use Composer\IO\BufferIO;
use PHPUnit\Framework\TestCase;
use SymPress\AssetCompiler\Config\BuildConfig;
use SymPress\AssetCompiler\Config\DependencyMode;
use SymPress\AssetCompiler\Config\PrecompiledAssetConfig;
use SymPress\AssetCompiler\Discovery\PackageWorkspace;
use SymPress\AssetCompiler\Precompiled\ArchiveExtractor;
use SymPress\AssetCompiler\Precompiled\Downloader;
use SymPress\AssetCompiler\Precompiled\GitHubAssetLocator;
use SymPress\AssetCompiler\Precompiled\PrecompiledAssetInstaller;
use SymPress\AssetCompiler\Precompiled\PrecompiledAssetSecurityException;
use Symfony\Component\Filesystem\Filesystem;
use ZipArchive;

final class PrecompiledAssetInstallerTest extends TestCase
{
    private ?string $workspacePath = null;

    #[\Override]
    protected function tearDown(): void
    {
        if ($this->workspacePath === null || !is_dir($this->workspacePath)) {
            return;
        }

        new Filesystem()->remove($this->workspacePath);
    }

    public function testInstallsLocalZipArchive(): void
    {
        $archive = $this->archive(['manifest.json' => '{"ok":true}']);
        $workspace = $this->workspace($archive);
        $installer = $this->installer();

        self::assertTrue($installer->install($workspace));
        self::assertFileExists($workspace->path . '/assets/manifest.json');
        self::assertSame('{"ok":true}', file_get_contents($workspace->path . '/assets/manifest.json'));
    }

    public function testVerifiesConfiguredChecksum(): void
    {
        $archive = $this->archive(['manifest.json' => '{"ok":true}']);
        $workspace = $this->workspace($archive, hash_file('sha256', $archive) ?: null);

        self::assertTrue($this->installer()->install($workspace));
        self::assertFileExists($workspace->path . '/assets/manifest.json');
    }

    public function testRejectsChecksumMismatch(): void
    {
        $archive = $this->archive(['manifest.json' => '{"ok":true}']);
        $workspace = $this->workspace($archive, str_repeat('0', 64));

        try {
            $this->installer()->install($workspace);
            self::fail('Expected checksum mismatch to fail hard.');
        } catch (PrecompiledAssetSecurityException $exception) {
            self::assertStringContainsString('checksum mismatch', $exception->getMessage());
        }

        self::assertFileDoesNotExist($workspace->path . '/assets/manifest.json');
    }

    public function testRejectsUnsafeZipEntryPaths(): void
    {
        $archive = $this->archive(['../manifest.json' => '{"unsafe":true}']);
        $workspace = $this->workspace($archive);

        $this->expectException(PrecompiledAssetSecurityException::class);
        $this->expectExceptionMessage('unsafe entry path');

        $this->installer()->install($workspace);
    }

    public function testKeepsExistingAssetsWhenArchiveExtractionFails(): void
    {
        $archive = $this->archive(['../manifest.json' => '{"unsafe":true}']);
        $workspace = $this->workspace($archive);
        mkdir($workspace->path . '/assets');
        file_put_contents($workspace->path . '/assets/old.txt', 'keep');

        try {
            $this->installer()->install($workspace);
            self::fail('Expected unsafe archive to fail hard.');
        } catch (PrecompiledAssetSecurityException) {
            self::assertFileExists($workspace->path . '/assets/old.txt');
            self::assertSame('keep', file_get_contents($workspace->path . '/assets/old.txt'));
        }

        self::assertFileDoesNotExist($workspace->path . '/manifest.json');
    }

    public function testRejectsUnsafeTargetPaths(): void
    {
        $archive = $this->archive(['manifest.json' => '{"ok":true}']);
        $workspace = $this->workspace($archive, target: '.');
        file_put_contents($workspace->path . '/keep.txt', 'keep');

        try {
            $this->installer()->install($workspace);
            self::fail('Expected unsafe target to fail hard.');
        } catch (PrecompiledAssetSecurityException $exception) {
            self::assertStringContainsString('Unsafe precompiled asset target', $exception->getMessage());
        }

        self::assertFileExists($workspace->path . '/keep.txt');
        self::assertFileDoesNotExist($workspace->path . '/manifest.json');
    }

    public function testRejectsInsecureHttpSources(): void
    {
        $workspace = $this->workspace('http://example.test/assets.zip');

        $this->expectException(PrecompiledAssetSecurityException::class);
        $this->expectExceptionMessage('Insecure non-HTTPS precompiled asset source');

        $this->installer()->install($workspace);
    }

    public function testProductionRemoteSourcesRequireChecksum(): void
    {
        $workspace = $this->workspace('https://example.test/assets.zip', requireChecksum: true);

        $this->expectException(PrecompiledAssetSecurityException::class);
        $this->expectExceptionMessage('Missing SHA-256 checksum');

        $this->installer()->install($workspace);
    }

    private function installer(): PrecompiledAssetInstaller
    {
        $filesystem = new Filesystem();
        $downloader = new Downloader();

        return new PrecompiledAssetInstaller(
            $downloader,
            new ArchiveExtractor($filesystem),
            new GitHubAssetLocator($downloader),
            new BufferIO(),
        );
    }

    /** @param array<string, string> $files */
    private function archive(array $files): string
    {
        $archive = $this->workspaceRoot() . '/precompiled.zip';
        $zip = new ZipArchive();
        $zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();

        return $archive;
    }

    private function workspace(
        string $archive,
        ?string $checksum = null,
        string $target = 'assets',
        bool $requireChecksum = false,
    ): PackageWorkspace {

        return new PackageWorkspace(
            name: 'vendor/package',
            type: 'wordpress-plugin',
            path: $this->workspaceRoot(),
            build: new BuildConfig(
                scripts: [],
                dependencyMode: DependencyMode::None,
                packageManager: null,
                packageManagerPreference: null,
                env: [],
                sourcePaths: [],
                timeout: 120,
                precompiledAssets: [
                    new PrecompiledAssetConfig('archive', $archive, $target, checksum: $checksum),
                ],
                requirePrecompiledChecksum: $requireChecksum,
            ),
            packageJson: [],
            version: '1.0.0',
            reference: 'abcdef',
        );
    }

    private function workspaceRoot(): string
    {
        if ($this->workspacePath === null) {
            $this->workspacePath = sys_get_temp_dir() . '/sympress_asset_compiler_precompiled_' . bin2hex(random_bytes(8));
            mkdir($this->workspacePath);
        }

        return $this->workspacePath;
    }
}
