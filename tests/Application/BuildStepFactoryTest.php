<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Tests\Application;

use PHPUnit\Framework\TestCase;
use SymPress\AssetCompiler\Application\BuildStepFactory;
use SymPress\AssetCompiler\Config\BuildConfig;
use SymPress\AssetCompiler\Config\DependencyMode;
use SymPress\AssetCompiler\Discovery\PackageWorkspace;
use SymPress\AssetCompiler\PackageManager\PackageManager;

final class BuildStepFactoryTest extends TestCase
{
    public function testCreatesInstallAndScriptSteps(): void
    {
        $workspace = $this->workspace(
            new BuildConfig(
                scripts: ['build'],
                dependencyMode: DependencyMode::Install,
                packageManager: null,
                packageManagerFallback: null,
                isolatedCache: false,
                env: ['APP_ENV' => 'test'],
                sourcePaths: [],
                timeout: 120,
            ),
        );

        $steps = BuildStepFactory::create($workspace, new PackageManager(PackageManager::NPM), true);

        self::assertCount(2, $steps);
        self::assertSame('install dependencies', $steps[0]->label);
        self::assertFalse($steps[0]->parallel);
        self::assertSame(['npm', 'install', '--no-package-lock'], $steps[0]->command);
        self::assertSame(['APP_ENV' => 'test'], $steps[0]->environment);
        self::assertSame('run build', $steps[1]->label);
        self::assertTrue($steps[1]->parallel);
        self::assertSame(['npm', 'run', 'build'], $steps[1]->command);
    }

    public function testCanSkipDependencyInstallation(): void
    {
        $workspace = $this->workspace(
            new BuildConfig(
                scripts: ['build'],
                dependencyMode: DependencyMode::Install,
                packageManager: null,
                packageManagerFallback: null,
                isolatedCache: false,
                env: [],
                sourcePaths: [],
                timeout: 120,
            ),
        );

        $steps = BuildStepFactory::create($workspace, new PackageManager(PackageManager::NPM), false);

        self::assertCount(1, $steps);
        self::assertSame(['npm', 'run', 'build'], $steps[0]->command);
    }

    public function testIsolatedCacheIsPassedToDependencyStep(): void
    {
        $workspace = $this->workspace(
            new BuildConfig(
                scripts: ['build'],
                dependencyMode: DependencyMode::Install,
                packageManager: null,
                packageManagerFallback: null,
                isolatedCache: true,
                env: [],
                sourcePaths: [],
                timeout: 120,
            ),
        );

        $steps = BuildStepFactory::create($workspace, new PackageManager(PackageManager::NPM), true);

        self::assertContains('--cache', $steps[0]->command);
        self::assertStringContainsString('/sympress-asset-compiler/cache/', implode(' ', $steps[0]->command));
    }

    public function testTimeoutIncrementIsAddedToSteps(): void
    {
        $workspace = $this->workspace(
            new BuildConfig(
                scripts: ['build'],
                dependencyMode: DependencyMode::Install,
                packageManager: null,
                packageManagerFallback: null,
                env: [],
                sourcePaths: [],
                timeout: 120,
            ),
        );

        $steps = BuildStepFactory::create($workspace, new PackageManager(PackageManager::NPM), true, 30);

        self::assertSame(150, $steps[0]->timeout);
        self::assertSame(150, $steps[1]->timeout);
    }

    private function workspace(BuildConfig $config): PackageWorkspace
    {
        return new PackageWorkspace(
            name: 'vendor/package',
            type: 'wordpress-plugin',
            path: sys_get_temp_dir(),
            build: $config,
            packageJson: [],
        );
    }
}
