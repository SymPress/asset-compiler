<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Tests\Precompiled;

use Composer\IO\BufferIO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use SymPress\AssetCompiler\Config\BuildConfig;
use SymPress\AssetCompiler\Config\DependencyMode;
use SymPress\AssetCompiler\Config\PrecompiledAssetConfig;
use SymPress\AssetCompiler\Discovery\PackageWorkspace;
use SymPress\AssetCompiler\Precompiled\ArchiveExtractor;
use SymPress\AssetCompiler\Precompiled\Downloader;
use SymPress\AssetCompiler\Precompiled\GitHubAssetLocator;
use SymPress\AssetCompiler\Precompiled\PrecompiledAssetInstaller;

final class PrecompiledAssetInstallerTest extends TestCase
{
    private ?string $workspacePath = null;

    protected function tearDown(): void
    {
        if ($this->workspacePath !== null && is_dir($this->workspacePath)) {
            (new Filesystem())->remove($this->workspacePath);
        }
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

    /**
     * @param array<string, string> $files
     */
    private function archive(array $files): string
    {
        $archive = $this->workspaceRoot() . '/precompiled.zip';
        $zip = new \ZipArchive();
        $zip->open($archive, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();

        return $archive;
    }

    private function workspace(string $archive): PackageWorkspace
    {
        return new PackageWorkspace(
            name: 'vendor/package',
            type: 'wordpress-plugin',
            path: $this->workspaceRoot(),
            build: new BuildConfig(
                scripts: [],
                dependencyMode: DependencyMode::None,
                packageManager: null,
                packageManagerFallback: null,
                env: [],
                sourcePaths: [],
                timeout: 120,
                precompiledAssets: [
                    new PrecompiledAssetConfig('archive', $archive, 'assets'),
                ],
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
