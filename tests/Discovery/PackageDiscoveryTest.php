<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Tests\Discovery;

use Composer\Composer;
use Composer\Installer\InstallationManager;
use Composer\Package\Package;
use Composer\Package\RootPackage;
use Composer\Repository\InstalledArrayRepository;
use Composer\Repository\RepositoryManager;
use PHPUnit\Framework\TestCase;
use SymPress\AssetCompiler\Config\ConfigReader;
use SymPress\AssetCompiler\Discovery\PackageDiscovery;
use Symfony\Component\Filesystem\Filesystem;

final class PackageDiscoveryTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . '/compiler-discovery-' . bin2hex(random_bytes(8));
        (new Filesystem())->mkdir($this->workspace . '/dependency');
        file_put_contents($this->workspace . '/package.json', '{"scripts":{"build":"echo root"}}');
        file_put_contents($this->workspace . '/dependency/package.json', '{"scripts":{"build":"echo dependency"}}');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->workspace);
    }

    public function testVendorCannotSelfAuthorizeAndIsNotParsedUntilRootAllowsIt(): void
    {
        $vendor = new Package('vendor/untrusted', '1.0.0', '1.0.0');
        $vendor->setType('wordpress-plugin');
        $vendor->setDistType('zip');
        $vendor->setExtra(['sympress.asset-compiler' => ['script' => 'evil'], 'kernel' => ['bundle' => 'Evil']]);
        file_put_contents($this->workspace . '/dependency/package.json', 'malformed');
        self::assertSame(['root/project'], $this->discover($vendor));
        file_put_contents($this->workspace . '/dependency/package.json', '{"scripts":{"build":"echo approved"}}');
        self::assertSame(['root/project', 'vendor/untrusted'], $this->discover($vendor, ['vendor/untrusted' => true]));
        self::assertSame(['root/project', 'vendor/untrusted'], $this->discover($vendor, ['vendor/*' => true]));
        self::assertSame(['root/project'], $this->discover($vendor, ['vendor/*' => true, 'vendor/untrusted' => false]));
    }

    public function testPathPackageAndRootRemainDefaultBuildCandidates(): void
    {
        $path = new Package('vendor/local', 'dev-main', 'dev-main');
        $path->setType('wordpress-plugin');
        $path->setDistType('path');
        self::assertSame(['root/project', 'vendor/local'], $this->discover($path));
    }

    public function testRootCanBeDisabledBeforeItsManifestIsParsed(): void
    {
        $path = new Package('vendor/local', 'dev-main', 'dev-main');
        $path->setType('wordpress-plugin');
        $path->setDistType('path');
        file_put_contents($this->workspace . '/package.json', 'malformed');
        self::assertSame(['vendor/local'], $this->discover($path, ['root/project' => false]));
        self::assertSame(['vendor/local'], $this->discover($path, ['root/*' => 'disabled']));
    }

    public function testExplicitRootSelectionCountsAsFoundAndOverridesItsScript(): void
    {
        $path = new Package('vendor/local', 'dev-main', 'dev-main');
        $path->setType('wordpress-plugin');
        $path->setDistType('path');
        self::assertSame(['root/project', 'vendor/local'], $this->discover($path, ['root/project' => true]));
        self::assertSame(['root/project', 'vendor/local'], $this->discover($path, ['root/project' => ['script' => 'custom']], ['custom']));
    }

    /**
     * @param array<string, mixed> $selection
     * @param list<string>|null $expectedRootScripts
     * @return list<string>
     */
    private function discover(Package $dependency, array $selection = [], ?array $expectedRootScripts = null): array
    {
        $root = new RootPackage('root/project', 'dev-main', 'dev-main');
        $root->setExtra(['sympress.asset-compiler' => ['packages' => $selection]]);
        $composer = new Composer();
        $composer->setPackage($root);
        $installation = $this->createStub(InstallationManager::class);
        $installation->method('getInstallPath')->willReturn($this->workspace . '/dependency');
        $composer->setInstallationManager($installation);
        $repository = $this->createStub(RepositoryManager::class);
        $repository->method('getLocalRepository')->willReturn(new InstalledArrayRepository([clone $dependency]));
        $composer->setRepositoryManager($repository);
        $reader = new ConfigReader(null, true);
        $discovery = new PackageDiscovery($composer, $reader, $reader->rootConfig($root, $this->workspace));

        $workspaces = $discovery->discover();
        if ($expectedRootScripts !== null) {
            self::assertSame($expectedRootScripts, $workspaces[0]->build->scripts);
        }

        return array_map(static fn ($package): string => $package->name, $workspaces);
    }
}
