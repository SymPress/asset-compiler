<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Application;

use SymPress\AssetCompiler\Config\DependencyMode;
use SymPress\AssetCompiler\Discovery\PackageWorkspace;
use SymPress\AssetCompiler\PackageManager\PackageManager;

final class BuildStepFactory
{
    /**
     * @return list<BuildStep>
     */
    public static function create(
        PackageWorkspace $workspace,
        PackageManager $manager,
        bool $installDependencies,
    ): array {
        $steps = [];
        $config = $workspace->build;

        if ($installDependencies && $config->dependencyMode !== DependencyMode::None) {
            $steps[] = new BuildStep(
                $config->dependencyMode === DependencyMode::Update ? 'update dependencies' : 'install dependencies',
                $config->dependencyMode === DependencyMode::Update
                    ? $manager->updateCommand($workspace)
                    : $manager->installCommand($workspace),
                $workspace->path,
                $config->timeout,
                $config->env,
            );
        }

        foreach ($config->scripts as $script) {
            $steps[] = new BuildStep(
                sprintf('run %s', $script),
                $manager->scriptCommand($script),
                $workspace->path,
                $config->timeout,
                $config->env,
            );
        }

        return $steps;
    }
}
