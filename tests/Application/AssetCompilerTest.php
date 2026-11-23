<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Tests\Application;

use Composer\Composer;
use Composer\Config;
use Composer\IO\BufferIO;
use Composer\Installer\InstallationManager;
use Composer\Package\RootPackage;
use Composer\Repository\InstalledArrayRepository;
use Composer\Repository\RepositoryManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SymPress\AssetCompiler\Application\AssetCompiler;
use SymPress\AssetCompiler\Application\AssetHasher;
use SymPress\AssetCompiler\Application\LockRepository;
use SymPress\AssetCompiler\Application\TaskRunner;
use SymPress\AssetCompiler\Config\ConfigReader;
use SymPress\AssetCompiler\Config\RootConfig;
use SymPress\AssetCompiler\Discovery\PackageDiscovery;
use SymPress\AssetCompiler\PackageManager\PackageManagerResolver;
use SymPress\AssetCompiler\Precompiled\ArchiveExtractor;
use SymPress\AssetCompiler\Precompiled\Downloader;
use SymPress\AssetCompiler\Precompiled\GitHubAssetLocator;
use SymPress\AssetCompiler\Precompiled\PrecompiledAssetInstaller;
use Symfony\Component\Filesystem\Filesystem;
use ZipArchive;

final class AssetCompilerTest extends TestCase
{
    private string $root = '';

    #[\Override]
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/sympress_asset_compiler_compiler_' . bin2hex(random_bytes(8));
        mkdir($this->root);
        file_put_contents($this->root . '/package.json', '{"scripts":{"build":"webpack"}}');
    }

    #[\Override]
    protected function tearDown(): void
    {
        new Filesystem()->remove($this->root);
    }

    public function testPrecompiledAssetsRestoreWithoutPackageManager(): void
    {
        $zip = new ZipArchive();
        $zip->open($this->root . '/assets.zip', ZipArchive::CREATE);
        $zip->addFromString('app.js', 'compiled');
        $zip->close();

        $result = $this->compiler([['adapter' => 'archive', 'source' => $this->root . '/assets.zip', 'target' => 'assets']])->compile();

        self::assertSame(1, $result->successfulTasks);
        self::assertFileExists($this->root . '/assets/app.js');
    }

    public function testMissingPackageManagerFailsWhenPrecompiledAssetsAreUnavailable(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/No supported package manager/');

        $this->compiler([['adapter' => 'archive', 'source' => $this->root . '/missing.zip', 'target' => 'assets']])->compile();
    }

    /** @param list<array<string, string>> $precompiled */
    private function compiler(array $precompiled): AssetCompiler
    {
        $root = new RootConfig(
            rootPath: $this->root,
            autoRun: true,
            autoDiscover: true,
            stopOnFailure: true,
            maxProcesses: 1,
            processPoll: 10000,
            isolatedCache: false,
            wipeNodeModules: false,
            timeoutIncrement: 0,
            packageManager: null,
            defaults: ['script' => 'build', 'precompiled' => $precompiled],
            packages: [],
            packageTypes: ['wordpress-plugin'],
            env: [],
        );
        $composer = new Composer();
        $composer->setPackage(new RootPackage('root/project', '1.0.0.0', '1.0.0'));
        $composer->setConfig(new Config());
        $composer->setInstallationManager($this->createStub(InstallationManager::class));
        $repositories = $this->createStub(RepositoryManager::class);
        $repositories->method('getLocalRepository')->willReturn(new InstalledArrayRepository());
        $composer->setRepositoryManager($repositories);
        $io = new BufferIO();
        $downloader = new Downloader();
        $filesystem = new Filesystem();

        return new AssetCompiler(
            $root,
            new PackageDiscovery($composer, new ConfigReader(null, true), $root),
            new AssetHasher($io),
            new LockRepository($filesystem, $io),
            new PackageManagerResolver(static fn (): bool => false),
            new TaskRunner($io),
            new PrecompiledAssetInstaller($downloader, new ArchiveExtractor($filesystem), new GitHubAssetLocator($downloader), $io),
            $io,
        );
    }
}
