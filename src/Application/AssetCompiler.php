<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Application;

use Composer\IO\IOInterface;
use SymPress\AssetCompiler\Config\DependencyMode;
use SymPress\AssetCompiler\Config\RootConfig;
use SymPress\AssetCompiler\Discovery\PackageDiscovery;
use SymPress\AssetCompiler\Discovery\PackageWorkspace;
use SymPress\AssetCompiler\PackageManager\PackageManagerResolution;
use SymPress\AssetCompiler\PackageManager\PackageManagerResolver;
use SymPress\AssetCompiler\Precompiled\PrecompiledAssetInstaller;

final readonly class AssetCompiler
{
    public function __construct(
        private RootConfig $rootConfig,
        private PackageDiscovery $packages,
        private AssetHasher $hasher,
        private LockRepository $locks,
        private PackageManagerResolver $packageManagers,
        private TaskRunner $runner,
        private PrecompiledAssetInstaller $precompiledAssets,
        private IOInterface $io,
    ) {
    }

    public function rootConfig(): RootConfig
    {
        return $this->rootConfig;
    }

    /**
     * @param list<string> $packagePatterns
     *
     * @throws \JsonException
     */
    public function compile(
        string $ignoreLock = '',
        array $packagePatterns = [],
        bool $installDependencies = true,
        bool $dryRun = false,
        bool $explain = false,
    ): CompilationResult {
        $workspaces = $this->filteredPackages($packagePatterns);
        $tasks = [];
        $skipped = 0;
        $precompiled = 0;
        $position = 0;

        foreach ($workspaces as $workspace) {
            $manager = $this->managerFor($workspace);
            $hash = $this->hasher->hash($workspace, $manager);
            $lock = $this->locks->status($workspace, $hash, $ignoreLock);

            if ($explain) {
                $this->explainWorkspace($workspace, $manager, $lock);
            }

            if ($lock->fresh) {
                ++$skipped;

                if (!$dryRun) {
                    $this->io->write(
                        sprintf('<comment>Skipping %s</comment> assets are already current.', $workspace->name),
                        true,
                        IOInterface::VERBOSE,
                    );
                }

                continue;
            }

            if (!$dryRun && $workspace->build->precompiledAssets !== [] && $this->precompiledAssets->install($workspace)) {
                $this->locks->write($workspace, $hash);
                ++$precompiled;
                continue;
            }

            if (!$manager instanceof PackageManagerResolution) {
                ++$skipped;

                if ($explain) {
                    $this->io->write(sprintf('<comment>%s</comment> skipped: no package-manager backed build steps.', $workspace->name));
                }

                continue;
            }

            $steps = BuildStepFactory::create(
                $workspace,
                $manager->manager,
                $installDependencies,
                $this->rootConfig->timeoutIncrement * $position,
            );
            ++$position;

            if ($steps === []) {
                ++$skipped;
                if ($explain) {
                    $this->io->write(sprintf('<comment>%s</comment> skipped: no runnable build steps.', $workspace->name));
                }
                continue;
            }

            if ($dryRun) {
                if (!$explain) {
                    $this->io->write(
                        sprintf('<info>%s</info> package manager: %s (%s)', $workspace->name, $manager->manager->name, $manager->reason),
                    );
                }

                foreach ($steps as $step) {
                    $this->io->write(sprintf('  %s', $step->displayCommand()));
                }
                continue;
            }

            $tasks[] = new BuildTask($workspace, $hash, $steps);
        }

        if ($dryRun) {
            return new CompilationResult(
                total: count($workspaces),
                successfulTasks: count($workspaces) - $skipped,
                skipped: $skipped,
                failed: 0,
            );
        }

        $result = $this->runner->run($tasks, $this->rootConfig);

        foreach ($result->successfulWorkspaces as $workspace) {
            $hash = $result->hashes[$workspace->name] ?? null;
            if (is_string($hash) && $hash !== '') {
                $this->locks->write($workspace, $hash);
            }
        }

        return new CompilationResult(
            total: count($workspaces),
            successfulTasks: $precompiled + count($result->successfulWorkspaces),
            skipped: $skipped,
            failed: $result->failed,
        );
    }

    /**
     * @param list<string> $packagePatterns
     * @return array<string, string>
     */
    public function hashes(array $packagePatterns = []): array
    {
        $hashes = [];

        foreach ($this->filteredPackages($packagePatterns) as $workspace) {
            $hashes[$workspace->name] = $this->hasher->hash($workspace, $this->managerFor($workspace));
        }

        ksort($hashes);

        return $hashes;
    }

    /**
     * @param list<string> $packagePatterns
     * @return list<PackageWorkspace>
     */
    private function filteredPackages(array $packagePatterns): array
    {
        $workspaces = $this->packages->discover();

        if ($packagePatterns === []) {
            return $workspaces;
        }

        return array_values(
            array_filter(
                $workspaces,
                static fn(PackageWorkspace $workspace): bool => self::matchesAny(
                    $workspace->name,
                    $packagePatterns,
                ),
            ),
        );
    }

    /**
     * @param list<string> $patterns
     */
    private static function matchesAny(string $name, array $patterns): bool
    {
        return array_any(
            $patterns,
            static fn(string $pattern): bool => $pattern === $name
                || fnmatch($pattern, $name, FNM_PATHNAME | FNM_PERIOD | FNM_CASEFOLD),
        );
    }

    private function managerFor(PackageWorkspace $workspace): ?PackageManagerResolution
    {
        if ($workspace->build->scripts === [] && $workspace->build->dependencyMode === DependencyMode::None) {
            return null;
        }

        return $this->packageManagers->resolve($workspace);
    }

    private function explainWorkspace(PackageWorkspace $workspace, ?PackageManagerResolution $manager, LockStatus $lock): void
    {
        $this->io->write(sprintf(
            '<info>%s</info> lock: %s%s',
            $workspace->name,
            $lock->reason,
            $workspace->build->precompiledAssets === [] ? '' : sprintf(', precompiled: %d configured', count($workspace->build->precompiledAssets)),
        ));

        if ($manager instanceof PackageManagerResolution) {
            $this->io->write(sprintf('  package manager: %s (%s)', $manager->manager->name, $manager->reason));
        }
    }
}
