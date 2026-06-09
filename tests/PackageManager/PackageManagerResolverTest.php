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

    #[\Override]
    protected function tearDown(): void
    {
        foreach ($this->workspacePaths as $workspacePath) {
            foreach (glob($workspacePath . '/*') ?: [] as $file) {
                unlink($file);
            }

            rmdir($workspacePath);
        }
    }

    public function testPackageJsonPackageManagerWinsOverRootPreference(): void
    {
        $workspace = $this->workspace(
            packageJson: ['packageManager' => 'npm@10.9.0'],
            packageManagerPreference: PackageManager::YARN,
        );

        $resolution = $this->resolver()->resolve($workspace);

        self::assertSame(PackageManager::NPM, $resolution->manager->name);
        self::assertSame('package.json packageManager', $resolution->reason);
    }

    public function testSingleNpmLockWinsOverRootPreference(): void
    {
        $workspace = $this->workspace(
            files: ['package-lock.json'],
            packageManagerPreference: PackageManager::YARN,
        );

        $resolution = $this->resolver()->resolve($workspace);

        self::assertSame(PackageManager::NPM, $resolution->manager->name);
        self::assertSame('lock file', $resolution->reason);
    }

    public function testRootPreferenceIsUsedWhenPackageHasNoManagerSignal(): void
    {
        $workspace = $this->workspace(packageManagerPreference: PackageManager::YARN);

        $resolution = $this->resolver()->resolve($workspace);

        self::assertSame(PackageManager::YARN, $resolution->manager->name);
        self::assertSame('root preference', $resolution->reason);
    }

    public function testNpmIsFallbackWhenPackageHasNoManagerSignalOrRootPreference(): void
    {
        $workspace = $this->workspace();

        $resolution = $this->resolver()->resolve($workspace);

        self::assertSame(PackageManager::NPM, $resolution->manager->name);
        self::assertSame('npm fallback', $resolution->reason);
    }

    public function testAmbiguousLockFilesUseRootPreference(): void
    {
        $workspace = $this->workspace(
            files: ['package-lock.json', 'yarn.lock'],
            packageManagerPreference: PackageManager::YARN,
        );

        $resolution = $this->resolver()->resolve($workspace);

        self::assertSame(PackageManager::YARN, $resolution->manager->name);
        self::assertSame('root preference', $resolution->reason);
    }

    public function testExplicitPackageConfigWinsOverDetectedSignals(): void
    {
        $workspace = $this->workspace(
            packageManager: PackageManager::PNPM,
            packageJson: ['packageManager' => 'npm@10.9.0'],
            files: ['yarn.lock'],
            packageManagerPreference: PackageManager::YARN,
        );

        $resolution = $this->resolver()->resolve($workspace);

        self::assertSame(PackageManager::PNPM, $resolution->manager->name);
        self::assertSame('package config', $resolution->reason);
    }

    private function resolver(): PackageManagerResolver
    {
        return new PackageManagerResolver(
            static fn(string $name): bool => in_array(
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
        ?string $packageManagerPreference = null,
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
                packageManagerPreference: $packageManagerPreference,
                env: [],
                sourcePaths: [],
                timeout: 120,
            ),
            packageJson: $packageJson,
        );
    }
}
