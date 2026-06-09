<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Application;

use SymPress\AssetCompiler\Config\DependencyMode;
use SymPress\AssetCompiler\Discovery\PackageWorkspace;
use SymPress\AssetCompiler\PackageManager\PackageManager;

final class BuildStepFactory
{
    private function __construct()
    {
    }

    /**
     * @return list<BuildStep>
     */
    public static function create(
        PackageWorkspace $workspace,
        PackageManager $manager,
        bool $installDependencies,
        int $timeoutIncrement = 0,
    ): array {
        $steps = [];
        $config = $workspace->build;
        $timeout = $config->timeout + max(0, $timeoutIncrement);

        if ($installDependencies && $config->dependencyMode !== DependencyMode::None) {
            $cacheDirectory = $config->isolatedCache ? self::cacheDirectory($workspace) : null;

            $steps[] = new BuildStep(
                $config->dependencyMode === DependencyMode::Update ? 'update dependencies' : 'install dependencies',
                $config->dependencyMode === DependencyMode::Update
                    ? $manager->updateCommand($workspace, $cacheDirectory)
                    : $manager->installCommand($workspace, $cacheDirectory),
                $workspace->path,
                $timeout,
                $config->env,
                false,
            );
        }

        foreach ($config->scripts as $script) {
            $steps[] = new BuildStep(
                sprintf('run %s', $script),
                $manager->scriptCommand($script),
                $workspace->path,
                $timeout,
                $config->env,
                true,
            );
        }

        return $steps;
    }

    private static function cacheDirectory(PackageWorkspace $workspace): string
    {
        return rtrim(sys_get_temp_dir(), '/') . '/sympress-asset-compiler/cache/' . sha1($workspace->name);
    }
}
