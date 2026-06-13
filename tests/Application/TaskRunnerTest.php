<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Tests\Application;

use Composer\IO\BufferIO;
use PHPUnit\Framework\TestCase;
use SymPress\AssetCompiler\Application\BuildStep;
use SymPress\AssetCompiler\Application\BuildStepFactory;
use SymPress\AssetCompiler\Application\BuildTask;
use SymPress\AssetCompiler\Application\TaskRunner;
use SymPress\AssetCompiler\Config\BuildConfig;
use SymPress\AssetCompiler\Config\DependencyMode;
use SymPress\AssetCompiler\Config\RootConfig;
use SymPress\AssetCompiler\Discovery\PackageWorkspace;

final class TaskRunnerTest extends TestCase
{
    private ?string $workspacePath = null;

    #[\Override]
    protected function tearDown(): void
    {
        if ($this->workspacePath === null || !is_dir($this->workspacePath)) {
            return;
        }

        $this->removeDirectory($this->workspacePath);
    }

    public function testRemovesNodeModulesCreatedByDependencyStep(): void
    {
        $workspace = $this->workspace();
        $task = new BuildTask($workspace, 'hash', [
            new BuildStep('install dependencies', ['php', '-r', 'mkdir("node_modules");'], $workspace->path, 60, [], false),
        ]);

        $result = new TaskRunner(new BufferIO())->run([$task], $this->rootConfig(wipeNodeModules: true));

        self::assertCount(1, $result->successfulWorkspaces);
        self::assertDirectoryDoesNotExist($workspace->path . '/node_modules');
    }

    public function testKeepsExistingNodeModules(): void
    {
        $workspace = $this->workspace();
        mkdir($workspace->path . '/node_modules');
        $task = new BuildTask($workspace, 'hash', [
            new BuildStep('install dependencies', ['php', '-r', 'file_put_contents("node_modules/installed", "yes");'], $workspace->path, 60, [], false),
        ]);

        $result = new TaskRunner(new BufferIO())->run([$task], $this->rootConfig(wipeNodeModules: true));

        self::assertCount(1, $result->successfulWorkspaces);
        self::assertFileExists($workspace->path . '/node_modules/installed');
    }

    public function testGroupedStrategyRemovesNodeModulesAfterPackagePipeline(): void
    {
        $workspace = $this->workspace();
        $task = new BuildTask($workspace, 'hash', [
            new BuildStep('install dependencies', ['php', '-r', 'mkdir("node_modules"); file_put_contents("node_modules/installed", "yes");'], $workspace->path, 60, [], false),
            new BuildStep('run build', ['php', '-r', 'if (!is_file("node_modules/installed")) { exit(1); } file_put_contents("built", "yes");'], $workspace->path, 60),
        ]);

        $result = new TaskRunner(new BufferIO())->run([
            $task,
        ], $this->rootConfig(
            wipeNodeModules: true,
            executionStrategy: RootConfig::EXECUTION_STRATEGY_GROUPED,
        ));

        self::assertCount(1, $result->successfulWorkspaces);
        self::assertFileExists($workspace->path . '/built');
        self::assertDirectoryDoesNotExist($workspace->path . '/node_modules');
    }

    public function testClearsIsolatedPackageManagerCacheAfterSuccessfulTask(): void
    {
        $workspace = $this->workspace(isolatedCache: true);
        $cacheDirectory = BuildStepFactory::cacheDirectoryFor($workspace);
        mkdir($cacheDirectory, 0777, true);
        file_put_contents($cacheDirectory . '/cached', 'yes');

        $task = new BuildTask($workspace, 'hash', [
            new BuildStep('run build', ['php', '-r', 'file_put_contents("built", "yes");'], $workspace->path, 60),
        ]);

        $result = new TaskRunner(new BufferIO())->run([
            $task,
        ], $this->rootConfig(
            wipeNodeModules: false,
            clearPackageManagerCache: true,
        ));

        self::assertCount(1, $result->successfulWorkspaces);
        self::assertFileExists($workspace->path . '/built');
        self::assertDirectoryDoesNotExist($cacheDirectory);
    }

    private function workspace(bool $isolatedCache = false): PackageWorkspace
    {
        $this->workspacePath = sys_get_temp_dir() . '/sympress_asset_compiler_runner_' . bin2hex(random_bytes(8));
        mkdir($this->workspacePath);

        return new PackageWorkspace(
            name: 'vendor/package',
            type: 'wordpress-plugin',
            path: $this->workspacePath,
            build: new BuildConfig([], DependencyMode::None, null, null, [], [], 120, $isolatedCache),
            packageJson: [],
        );
    }

    private function rootConfig(
        bool $wipeNodeModules,
        bool $clearPackageManagerCache = false,
        string $executionStrategy = RootConfig::EXECUTION_STRATEGY_STAGED,
    ): RootConfig {

        return new RootConfig(
            rootPath: '/project',
            autoRun: true,
            autoDiscover: true,
            stopOnFailure: true,
            maxProcesses: 4,
            processPoll: 10000,
            isolatedCache: false,
            wipeNodeModules: $wipeNodeModules,
            timeoutIncrement: 0,
            packageManager: null,
            defaults: [],
            packages: [],
            packageTypes: ['wordpress-plugin'],
            env: [],
            clearPackageManagerCache: $clearPackageManagerCache,
            executionStrategy: $executionStrategy,
        );
    }

    private function removeDirectory(string $path): void
    {
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            $child = $path . '/' . $entry;
            is_dir($child) ? $this->removeDirectory($child) : unlink($child);
        }

        rmdir($path);
    }
}
