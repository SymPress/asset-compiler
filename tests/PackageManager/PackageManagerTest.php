<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Tests\PackageManager;

use PHPUnit\Framework\TestCase;
use SymPress\AssetCompiler\Config\BuildConfig;
use SymPress\AssetCompiler\Config\DependencyMode;
use SymPress\AssetCompiler\Discovery\PackageWorkspace;
use SymPress\AssetCompiler\PackageManager\PackageManager;

final class PackageManagerTest extends TestCase
{
    private ?string $workspacePath = null;

    #[\Override]
    protected function tearDown(): void
    {
        if ($this->workspacePath === null || !is_dir($this->workspacePath)) {
            return;
        }

        array_map('unlink', glob($this->workspacePath . '/*') ?: []);
        rmdir($this->workspacePath);
    }

    public function testNpmUsesCiWhenLockFileExists(): void
    {
        $workspace = $this->workspaceWithFile('package-lock.json');

        self::assertSame(['npm', 'ci', '--ignore-scripts'], new PackageManager(PackageManager::NPM)->installCommand($workspace));
    }

    public function testNpmAvoidsWritingLockWhenNoLockFileExists(): void
    {
        $workspace = $this->workspace();

        self::assertSame(
            ['npm', 'install', '--no-package-lock', '--ignore-scripts'],
            new PackageManager(PackageManager::NPM)->installCommand($workspace),
        );
    }

    public function testScriptCommandSplitsArguments(): void
    {
        self::assertSame(
            ['npm', 'run', 'build', '--', '--mode', 'production'],
            new PackageManager(PackageManager::NPM)->scriptCommand('build -- --mode production'),
        );
    }

    public function testYarnInstallUsesSharedMutex(): void
    {
        $workspace = $this->workspace();

        self::assertSame(
            ['yarn', 'install', '--mutex', 'file:/tmp/sympress-asset-compiler-yarn.lock', '--ignore-scripts'],
            new PackageManager(PackageManager::YARN, '1.22.22')->installCommand($workspace),
        );
    }

    public function testEveryProductionManagerRequiresItsOwnLock(): void
    {
        $workspace = $this->workspace();
        $production = new PackageWorkspace($workspace->name, $workspace->type, $workspace->path, new BuildConfig([], DependencyMode::Install, null, null, [], [], 120, production: true), []);
        foreach ([PackageManager::NPM, PackageManager::PNPM, PackageManager::YARN] as $name) {
            $manager = new PackageManager($name, $name === PackageManager::YARN ? '4.9.4' : null);
            try {
                $manager->installCommand($production);
                self::fail('Production manager accepted a missing lock: ' . $name);
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString('lockfile', $exception->getMessage());
            }
            try {
                $manager->updateCommand($production);
                self::fail('Production manager updated dependencies: ' . $name);
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString('cannot update', $exception->getMessage());
            }
        }
    }

    public function testModernYarnUsesImmutableInstallAndEnvironmentLifecycleControl(): void
    {
        $workspace = $this->workspaceWithFile('yarn.lock');
        self::assertSame(['env', 'YARN_ENABLE_SCRIPTS=false', 'yarn', 'install', '--immutable', '--mode=skip-build'], new PackageManager(PackageManager::YARN, '4.9.4')->installCommand($workspace));
        self::assertSame(['pnpm', 'install', '--ignore-scripts'], new PackageManager(PackageManager::PNPM)->installCommand($workspace));
        self::assertSame(['npm', 'update', '--no-save', '--ignore-scripts'], new PackageManager(PackageManager::NPM)->updateCommand($workspace));
    }

    private function workspaceWithFile(string $file): PackageWorkspace
    {
        $workspace = $this->workspace();
        touch($workspace->path . '/' . $file);

        return $workspace;
    }

    private function workspace(): PackageWorkspace
    {
        $this->workspacePath = sys_get_temp_dir() . '/sympress_asset_compiler_' . bin2hex(random_bytes(8));
        mkdir($this->workspacePath);

        return new PackageWorkspace(
            name: 'vendor/package',
            type: 'wordpress-plugin',
            path: $this->workspacePath,
            build: new BuildConfig([], DependencyMode::None, null, null, [], [], 120),
            packageJson: [],
        );
    }
}
