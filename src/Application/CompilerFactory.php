<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Application;

use Composer\Composer;
use Composer\Factory as ComposerFactory;
use Composer\IO\IOInterface;
use Symfony\Component\Filesystem\Filesystem;
use SymPress\AssetCompiler\Config\ConfigReader;
use SymPress\AssetCompiler\Discovery\PackageDiscovery;
use SymPress\AssetCompiler\PackageManager\PackageManagerResolver;

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
        $reader = new ConfigReader($mode, $devMode);
        $rootConfig = $reader->rootConfig($composer->getPackage(), $rootPath);

        return new AssetCompiler(
            $rootConfig,
            new PackageDiscovery($composer, $reader, $rootConfig),
            new AssetHasher($io),
            new LockRepository($filesystem, $io),
            new PackageManagerResolver(),
            new TaskRunner($io),
            $io,
        );
    }

    private static function rootPath(Composer $composer): string
    {
        $composerFile = ComposerFactory::getComposerFile();
        $rootPath = dirname($composerFile);

        if ($rootPath === '.' || $rootPath === '') {
            $vendorDir = $composer->getConfig()->get('vendor-dir');

            return is_string($vendorDir) ? dirname($vendorDir) : getcwd();
        }

        if (!str_starts_with($rootPath, '/')) {
            $rootPath = getcwd() . '/' . $rootPath;
        }

        $real = realpath($rootPath);

        return is_string($real) ? $real : $rootPath;
    }
}
