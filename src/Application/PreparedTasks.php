<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Application;

use SymPress\AssetCompiler\Discovery\PackageWorkspace;

final readonly class PreparedTasks
{
    /**
     * @param list<PackageWorkspace> $successfulWorkspaces
     * @param array<string, string> $hashes
     * @param list<BuildTask> $parallelTasks
     */
    public function __construct(
        public array $successfulWorkspaces,
        public array $hashes,
        public array $parallelTasks,
        public int $failed,
    ) {
    }
}
