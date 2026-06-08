<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Discovery;

use Composer\Composer;
use Composer\Installer\InstallationManager;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\Repository\RepositoryInterface;
use SymPress\AssetCompiler\Config\ConfigReader;
use SymPress\AssetCompiler\Config\RootConfig;

final readonly class PackageDiscovery
{
    private InstallationManager $installationManager;

    private RepositoryInterface $repository;

    public function __construct(
        private Composer $composer,
        private ConfigReader $configReader,
        private RootConfig $rootConfig,
    ) {
        $this->installationManager = $composer->getInstallationManager();
        $this->repository = $composer->getRepositoryManager()->getLocalRepository();
    }

    /**
     * @return list<PackageWorkspace>
     */
    public function discover(): array
    {
        $workspaces = [];
        $matchedPatterns = [];
        $rootPackage = $this->composer->getPackage();
        $rootWorkspace = $this->workspaceForRootPackage($rootPackage);

        if ($rootWorkspace instanceof PackageWorkspace) {
            $workspaces[$rootWorkspace->name] = $rootWorkspace;
        }

        foreach ($this->repository->getPackages() as $package) {
            $path = $this->pathForPackage($package);

            if ($path === null) {
                continue;
            }

            $packageJson = $this->packageJson($path);
            $selection = $this->selection($package->getName());

            if ($selection instanceof RootPackageSelection && $selection->pattern !== '') {
                $matchedPatterns[$selection->pattern] = true;
            }

            if (!$this->shouldInspect($package, $packageJson, $selection)) {
                continue;
            }

            $build = $this->configReader->buildConfig(
                $package,
                $this->rootConfig,
                $packageJson,
                $selection?->override,
                $selection?->forceDefaults ?? false,
                $path,
            );

            if (!$build instanceof \SymPress\AssetCompiler\Config\BuildConfig) {
                continue;
            }

            $workspaces[$package->getName()] = new PackageWorkspace(
                $package->getName(),
                $package->getType(),
                $path,
                $build,
                $packageJson,
            );
        }

        $this->assertRequiredPackagesWereFound($matchedPatterns);

        usort(
            $workspaces,
            static fn (PackageWorkspace $left, PackageWorkspace $right): int => strcasecmp($left->name, $right->name),
        );

        return array_values($workspaces);
    }

    private function workspaceForRootPackage(RootPackageInterface $package): ?PackageWorkspace
    {
        $packageJson = $this->packageJson($this->rootConfig->rootPath);

        if ($packageJson === []) {
            return null;
        }

        $build = $this->configReader->buildConfig(
            $package,
            $this->rootConfig,
            $packageJson,
            null,
            false,
            $this->rootConfig->rootPath,
        );

        if (!$build instanceof \SymPress\AssetCompiler\Config\BuildConfig) {
            return null;
        }

        return new PackageWorkspace(
            $package->getName(),
            $package->getType(),
            $this->rootConfig->rootPath,
            $build,
            $packageJson,
        );
    }

    private function pathForPackage(PackageInterface $package): ?string
    {
        $path = $this->installationManager->getInstallPath($package);

        if (!is_string($path) || $path === '') {
            return null;
        }

        if (!str_starts_with($path, '/')) {
            $path = $this->rootConfig->rootPath . '/' . $path;
        }

        $real = realpath($path);
        $path = is_string($real) ? $real : $path;

        return is_dir($path) ? $this->normalizePath($path) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function packageJson(string $path): array
    {
        $file = rtrim($path, '/') . '/package.json';

        if (!is_file($file) || !is_readable($file)) {
            return [];
        }

        $contents = file_get_contents($file);

        if (!is_string($contents) || trim($contents) === '') {
            return [];
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function shouldInspect(
        PackageInterface $package,
        array $packageJson,
        ?RootPackageSelection $selection,
    ): bool {
        if ($selection?->disabled) {
            return false;
        }

        if ($selection?->explicit) {
            return $packageJson !== [];
        }

        $path = $this->pathForPackage($package);
        $packageExtra = $this->configReader->packageExtra($package, $path);

        if ($packageExtra !== []) {
            return $packageJson !== [];
        }

        if (!$this->rootConfig->autoDiscover || !$this->hasBuildScript($packageJson)) {
            return false;
        }

        if ($this->isProjectPackage($package)) {
            return true;
        }

        return false;
    }

    private function selection(string $packageName): ?RootPackageSelection
    {
        foreach ($this->rootConfig->packages as $pattern => $raw) {
            if (!is_string($pattern) || !$this->matches($packageName, $pattern)) {
                continue;
            }

            return $this->selectionFromRaw($pattern, $raw);
        }

        return null;
    }

    private function selectionFromRaw(string $pattern, mixed $raw): RootPackageSelection
    {
        if ($raw === false || $raw === 'disabled') {
            return new RootPackageSelection(null, false, true, true, $pattern);
        }

        if ($raw === true || $raw === 'package-or-defaults') {
            return new RootPackageSelection(null, false, false, true, $pattern);
        }

        if ($raw === '$force-defaults' || $raw === '$defaults') {
            return new RootPackageSelection(null, true, false, true, $pattern);
        }

        if (is_string($raw)) {
            return new RootPackageSelection(['script' => $raw], false, false, true, $pattern);
        }

        if (is_array($raw) && array_is_list($raw)) {
            return new RootPackageSelection(['script' => $raw], false, false, true, $pattern);
        }

        return new RootPackageSelection(is_array($raw) ? $raw : null, false, false, true, $pattern);
    }

    /**
     * @param array<string, bool> $matchedPatterns
     */
    private function assertRequiredPackagesWereFound(array $matchedPatterns): void
    {
        if (!$this->rootConfig->stopOnFailure) {
            return;
        }

        $missing = [];

        foreach ($this->rootConfig->packages as $pattern => $raw) {
            if (!is_string($pattern) || str_contains($pattern, '*')) {
                continue;
            }

            if ($raw === false || $raw === 'disabled') {
                continue;
            }

            if (!isset($matchedPatterns[$pattern])) {
                $missing[] = $pattern;
            }
        }

        if ($missing === []) {
            return;
        }

        throw new \RuntimeException(
            sprintf('Asset compiler package config references missing Composer package(s): %s.', implode(', ', $missing)),
        );
    }

    private function hasBuildScript(array $packageJson): bool
    {
        $scripts = $packageJson['scripts'] ?? null;

        return is_array($scripts) && is_string($scripts['build'] ?? null);
    }

    private function requiresAssetsPackage(PackageInterface $package): bool
    {
        foreach ($package->getRequires() as $link) {
            if ($link->getTarget() === 'sympress/assets') {
                return true;
            }
        }

        return false;
    }

    private function isProjectPackage(PackageInterface $package): bool
    {
        if ($this->requiresAssetsPackage($package) || $this->hasKernelMetadata($package)) {
            return true;
        }

        return in_array($package->getType(), $this->rootConfig->packageTypes, true)
            && $package->getDistType() === 'path';
    }

    private function hasKernelMetadata(PackageInterface $package): bool
    {
        $kernel = $package->getExtra()['kernel'] ?? null;

        return is_array($kernel) && is_string($kernel['bundle'] ?? null);
    }

    private function matches(string $packageName, string $pattern): bool
    {
        return $pattern === $packageName
            || fnmatch($pattern, $packageName, FNM_PATHNAME | FNM_PERIOD | FNM_CASEFOLD);
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        return rtrim(preg_replace('~/+~', '/', $path) ?: $path, '/');
    }
}
