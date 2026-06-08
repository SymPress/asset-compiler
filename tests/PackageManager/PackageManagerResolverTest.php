<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Tests\PackageManager;

use PHPUnit\Framework\TestCase;
use SymPress\AssetCompiler\Config\BuildConfig;
use SymPress\AssetCompiler\Config\DependencyMode;
use SymPress\AssetCompiler\Discovery\PackageWorkspace;
use SymPress\AssetCompiler\PackageManager\PackageManager;
use SymPress\AssetCompiler\PackageManager\PackageManagerResolver;

final class PackageManagerResolverTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $workspacePaths = [];

    protected function tearDown(): void
    {
        foreach ($this->workspacePaths as $workspacePath) {
            foreach (glob($workspacePath . '/*') ?: [] as $file) {
                unlink($file);
            }

            rmdir($workspacePath);
        }
    }

    public function testPackageJsonPackageManagerWinsOverRootFallback(): void
    {
        $workspace = $this->workspace(
            packageJson: ['packageManager' => 'npm@10.9.0'],
            packageManagerFallback: PackageManager::YARN,
        );

        self::assertSame(PackageManager::NPM, $this->resolver()->resolve($workspace)->name);
    }

    public function testSingleNpmLockWinsOverRootFallback(): void
    {
        $workspace = $this->workspace(
            files: ['package-lock.json'],
            packageManagerFallback: PackageManager::YARN,
        );

        self::assertSame(PackageManager::NPM, $this->resolver()->resolve($workspace)->name);
    }

    public function testRootFallbackIsUsedWhenPackageHasNoManagerSignal(): void
    {
        $workspace = $this->workspace(packageManagerFallback: PackageManager::YARN);

        self::assertSame(PackageManager::YARN, $this->resolver()->resolve($workspace)->name);
    }

    public function testAmbiguousLockFilesUseRootFallback(): void
    {
        $workspace = $this->workspace(
            files: ['package-lock.json', 'yarn.lock'],
            packageManagerFallback: PackageManager::YARN,
        );

        self::assertSame(PackageManager::YARN, $this->resolver()->resolve($workspace)->name);
    }

    public function testExplicitPackageConfigWinsOverDetectedSignals(): void
    {
        $workspace = $this->workspace(
            packageManager: PackageManager::PNPM,
            packageJson: ['packageManager' => 'npm@10.9.0'],
            files: ['yarn.lock'],
            packageManagerFallback: PackageManager::YARN,
        );

        self::assertSame(PackageManager::PNPM, $this->resolver()->resolve($workspace)->name);
    }

    private function resolver(): PackageManagerResolver
    {
        return new PackageManagerResolver(
            static fn (string $name): bool => in_array(
                $name,
                [PackageManager::NPM, PackageManager::YARN, PackageManager::PNPM],
                true,
            ),
        );
    }

    /**
     * @param array<string, mixed> $packageJson
     * @param list<string> $files
     */
    private function workspace(
        ?string $packageManager = null,
        array $packageJson = [],
        array $files = [],
        ?string $packageManagerFallback = null,
    ): PackageWorkspace {
        $path = sys_get_temp_dir() . '/sympress_asset_compiler_resolver_' . bin2hex(random_bytes(8));
        mkdir($path);
        $this->workspacePaths[] = $path;

        foreach ($files as $file) {
            touch($path . '/' . $file);
        }

        return new PackageWorkspace(
            name: 'vendor/package',
            type: 'wordpress-plugin',
            path: $path,
            build: new BuildConfig(
                scripts: [],
                dependencyMode: DependencyMode::None,
                packageManager: $packageManager,
                packageManagerFallback: $packageManagerFallback,
                env: [],
                sourcePaths: [],
                timeout: 120,
            ),
            packageJson: $packageJson,
        );
    }
}
