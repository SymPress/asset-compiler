<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Tests\Config;

use Composer\Package\Package;
use Composer\Package\RootPackage;
use PHPUnit\Framework\TestCase;
use SymPress\AssetCompiler\Config\ConfigReader;
use SymPress\AssetCompiler\Config\DependencyMode;
use SymPress\AssetCompiler\Config\RootConfig;

final class ConfigReaderTest extends TestCase
{
    public function testMinimalRootConfigUsesBuiltInDefaults(): void
    {
        $package = new RootPackage('acme/root', '1.0.0.0', '1.0.0');
        $package->setExtra([
            RootConfig::EXTRA_KEY => [
                'auto-run' => true,
                'package-manager' => 'yarn',
            ],
        ]);

        $config = (new ConfigReader(null, true))->rootConfig($package, '/project');

        self::assertTrue($config->autoRun);
        self::assertTrue($config->autoDiscover);
        self::assertSame(4, $config->maxProcesses);
        self::assertFalse($config->isolatedCache);
        self::assertFalse($config->wipeNodeModules);
        self::assertSame(0, $config->timeoutIncrement);
        self::assertSame('yarn', $config->packageManager);
        self::assertSame([], $config->defaults);
        self::assertSame(['wordpress-plugin', 'wordpress-theme', 'wordpress-muplugin'], $config->packageTypes);
    }

    public function testBuildConfigFallsBackToPackageJsonBuildScript(): void
    {
        $root = new RootConfig(
            rootPath: '/project',
            autoRun: true,
            autoDiscover: true,
            stopOnFailure: true,
            maxProcesses: 4,
            processPoll: 100000,
            isolatedCache: false,
            wipeNodeModules: false,
            timeoutIncrement: 0,
            packageManager: 'yarn',
            defaults: [],
            packages: [],
            packageTypes: ['wordpress-plugin'],
            env: [],
        );

        $config = (new ConfigReader(null, true))->buildConfig(
            new Package('acme/package', '1.0.0.0', '1.0.0'),
            $root,
            ['scripts' => ['build' => 'encore production']],
            null,
            false,
        );

        self::assertNotNull($config);
        self::assertSame(['build'], $config->scripts);
        self::assertSame(DependencyMode::Install, $config->dependencyMode);
        self::assertNull($config->packageManager);
        self::assertSame('yarn', $config->packageManagerPreference);
        self::assertFalse($config->isolatedCache);
        self::assertSame([], $config->sourcePaths);
    }

    public function testScriptInterpolatesDefaultEnvironment(): void
    {
        $root = new RootConfig(
            rootPath: '/project',
            autoRun: true,
            autoDiscover: true,
            stopOnFailure: true,
            maxProcesses: 4,
            processPoll: 100000,
            isolatedCache: true,
            wipeNodeModules: false,
            timeoutIncrement: 0,
            packageManager: null,
            defaults: [],
            packages: [],
            packageTypes: ['wordpress-plugin'],
            env: ['BUILD_TARGET' => 'admin'],
        );

        $package = new Package('acme/package', '1.0.0.0', '1.0.0');
        $package->setExtra([
            RootConfig::EXTRA_KEY => [
                'script' => 'build -- ${BUILD_TARGET}',
            ],
        ]);

        $config = (new ConfigReader(null, true))->buildConfig(
            $package,
            $root,
            ['scripts' => ['build' => 'encore production']],
            null,
            false,
        );

        self::assertNotNull($config);
        self::assertSame(['build -- admin'], $config->scripts);
        self::assertTrue($config->isolatedCache);
    }

    public function testPackageConfigFileOverridesComposerExtra(): void
    {
        $path = sys_get_temp_dir() . '/sympress_asset_compiler_config_' . bin2hex(random_bytes(8));
        mkdir($path);

        try {
            file_put_contents($path . '/asset-compiler.json', json_encode(['script' => 'from-file'], JSON_THROW_ON_ERROR));

            $package = new Package('acme/package', '1.0.0.0', '1.0.0');
            $package->setExtra([
                RootConfig::EXTRA_KEY => [
                    'script' => 'from-composer',
                ],
            ]);

            self::assertSame(
                ['script' => 'from-file'],
                (new ConfigReader(null, true))->packageExtra($package, $path),
            );
        } finally {
            unlink($path . '/asset-compiler.json');
            rmdir($path);
        }
    }

    public function testBuildConfigParsesPrecompiledAssets(): void
    {
        $root = new RootConfig(
            rootPath: '/project',
            autoRun: true,
            autoDiscover: true,
            stopOnFailure: true,
            maxProcesses: 4,
            processPoll: 100000,
            isolatedCache: false,
            wipeNodeModules: false,
            timeoutIncrement: 0,
            packageManager: null,
            defaults: [],
            packages: [],
            packageTypes: ['wordpress-plugin'],
            env: [],
        );
        $package = new Package('acme/package', '1.0.0.0', '1.0.0');
        $package->setExtra([
            RootConfig::EXTRA_KEY => [
                'script' => 'build',
                'pre-compiled' => [
                    'adapter' => 'archive',
                    'source' => 'https://example.test/assets-${version}.zip',
                    'target' => 'assets',
                    'stability' => 'stable',
                    'config' => ['clean-target' => false],
                ],
            ],
        ]);

        $config = (new ConfigReader(null, true))->buildConfig(
            $package,
            $root,
            ['scripts' => ['build' => 'encore production']],
            null,
            false,
        );

        self::assertNotNull($config);
        self::assertCount(1, $config->precompiledAssets);
        self::assertSame('archive', $config->precompiledAssets[0]->adapter);
        self::assertSame('https://example.test/assets-${version}.zip', $config->precompiledAssets[0]->source);
        self::assertFalse($config->precompiledAssets[0]->config['clean-target']);
    }
}
