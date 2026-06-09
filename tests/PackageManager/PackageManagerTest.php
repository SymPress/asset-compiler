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
        if ($this->workspacePath !== null && is_dir($this->workspacePath)) {
            array_map('unlink', glob($this->workspacePath . '/*') ?: []);
            rmdir($this->workspacePath);
        }
    }

    public function testNpmUsesCiWhenLockFileExists(): void
    {
        $workspace = $this->workspaceWithFile('package-lock.json');

        self::assertSame(['npm', 'ci'], (new PackageManager(PackageManager::NPM))->installCommand($workspace));
    }

    public function testNpmAvoidsWritingLockWhenNoLockFileExists(): void
    {
        $workspace = $this->workspace();

        self::assertSame(
            ['npm', 'install', '--no-package-lock'],
            (new PackageManager(PackageManager::NPM))->installCommand($workspace),
        );
    }

    public function testScriptCommandSplitsArguments(): void
    {
        self::assertSame(
            ['npm', 'run', 'build', '--', '--mode', 'production'],
            (new PackageManager(PackageManager::NPM))->scriptCommand('build -- --mode production'),
        );
    }

    public function testYarnInstallUsesSharedMutex(): void
    {
        $workspace = $this->workspace();

        self::assertSame(
            ['yarn', 'install', '--mutex', 'file:/tmp/sympress-asset-compiler-yarn.lock'],
            (new PackageManager(PackageManager::YARN))->installCommand($workspace),
        );
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
