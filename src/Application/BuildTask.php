<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Application;

use SymPress\AssetCompiler\Discovery\PackageWorkspace;

final readonly class BuildTask
{
    /**
     * @param list<BuildStep> $steps
     */
    public function __construct(
        public PackageWorkspace $workspace,
        public string $hash,
        public array $steps,
    ) {
    }
}
