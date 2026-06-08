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
        self::assertSame('yarn', $config->packageManagerFallback);
        self::assertSame([], $config->sourcePaths);
    }
}
