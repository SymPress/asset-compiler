<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Application;

use Composer\Composer;
use Composer\Factory as ComposerFactory;
use Composer\IO\IOInterface;
use SymPress\AssetCompiler\Config\ConfigReader;
use SymPress\AssetCompiler\Discovery\PackageDiscovery;
use SymPress\AssetCompiler\PackageManager\PackageManagerResolver;
use SymPress\AssetCompiler\Precompiled\ArchiveExtractor;
use SymPress\AssetCompiler\Precompiled\Downloader;
use SymPress\AssetCompiler\Precompiled\GitHubAssetLocator;
use SymPress\AssetCompiler\Precompiled\PrecompiledAssetInstaller;
use Symfony\Component\Filesystem\Filesystem;

final class CompilerFactory
{
    public static function create(
        Composer $composer,
        IOInterface $io,
        ?string $mode,
        bool $devMode,
    ): AssetCompiler {

        $filesystem = new Filesystem();
        $rootPath = self::rootPath($composer);
        $reader = new ConfigReader($mode ?? self::modeFromEnvironment(), $devMode);
        $rootConfig = $reader->rootConfig($composer->getPackage(), $rootPath);
        $downloader = new Downloader();
        $github = new GitHubAssetLocator($downloader);

        return new AssetCompiler(
            $rootConfig,
            new PackageDiscovery($composer, $reader, $rootConfig),
            new AssetHasher($io),
            new LockRepository($filesystem, $io),
            new PackageManagerResolver(),
            new TaskRunner($io),
            new PrecompiledAssetInstaller($downloader, new ArchiveExtractor($filesystem), $github, $io),
            $io,
        );
    }

    private static function rootPath(Composer $composer): string
    {
        $composerFile = ComposerFactory::getComposerFile();
        $rootPath = dirname($composerFile);

        if ($rootPath === '.' || $rootPath === '') {
            $vendorDir = $composer->getConfig()->get('vendor-dir');

            if (is_string($vendorDir) && $vendorDir !== '') {
                return dirname($vendorDir);
            }

            $cwd = getcwd();

            return is_string($cwd) ? $cwd : '.';
        }

        if (!str_starts_with($rootPath, '/')) {
            $rootPath = getcwd() . '/' . $rootPath;
        }

        $real = realpath($rootPath);

        return is_string($real) ? $real : $rootPath;
    }

    private static function modeFromEnvironment(): ?string
    {
        foreach (['COMPOSER_ASSETS_COMPILER', 'COMPOSER_ASSET_COMPILER'] as $name) {
            $value = getenv($name);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }
}
