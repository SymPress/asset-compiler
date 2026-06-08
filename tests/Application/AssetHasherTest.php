<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Tests\Application;

use Composer\IO\BufferIO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use SymPress\AssetCompiler\Application\AssetHasher;
use SymPress\AssetCompiler\Config\BuildConfig;
use SymPress\AssetCompiler\Config\DependencyMode;
use SymPress\AssetCompiler\Discovery\PackageWorkspace;
use SymPress\AssetCompiler\PackageManager\PackageManager;
use SymPress\AssetCompiler\PackageManager\PackageManagerResolution;

final class AssetHasherTest extends TestCase
{
    private ?string $workspacePath = null;

    protected function tearDown(): void
    {
        if ($this->workspacePath !== null) {
            (new Filesystem())->remove($this->workspacePath);
        }
    }

    public function testResolvedPackageManagerInvalidatesHash(): void
    {
        $workspace = $this->workspace();
        $hasher = new AssetHasher(new BufferIO());

        $npmHash = $hasher->hash(
            $workspace,
            new PackageManagerResolution(new PackageManager(PackageManager::NPM), 'npm fallback'),
        );
        $yarnHash = $hasher->hash(
            $workspace,
            new PackageManagerResolution(new PackageManager(PackageManager::YARN), 'root preference'),
        );

        self::assertNotSame($npmHash, $yarnHash);
    }

    public function testDefaultDiscoveryIncludesSrcDirectory(): void
    {
        $workspace = $this->workspace();
        mkdir($workspace->path . '/src');
        file_put_contents($workspace->path . '/src/index.js', 'console.log("one");');

        $hasher = new AssetHasher(new BufferIO());
        $before = $hasher->hash($workspace);

        file_put_contents($workspace->path . '/src/index.js', 'console.log("two");');

        self::assertNotSame($before, $hasher->hash($workspace));
    }

    private function workspace(): PackageWorkspace
    {
        $this->workspacePath = sys_get_temp_dir() . '/sympress_asset_compiler_hasher_' . bin2hex(random_bytes(8));
        mkdir($this->workspacePath);
        file_put_contents($this->workspacePath . '/package.json', '{"scripts":{"build":"webpack"}}');

        return new PackageWorkspace(
            name: 'vendor/package',
            type: 'wordpress-plugin',
            path: $this->workspacePath,
            build: new BuildConfig(
                scripts: ['build'],
                dependencyMode: DependencyMode::Install,
                packageManager: null,
                packageManagerPreference: null,
                env: [],
                sourcePaths: [],
                timeout: 120,
            ),
            packageJson: ['scripts' => ['build' => 'webpack']],
        );
    }
}
