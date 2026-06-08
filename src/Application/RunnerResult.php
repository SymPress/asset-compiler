<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Application;

use SymPress\AssetCompiler\Discovery\PackageWorkspace;

final readonly class RunnerResult
{
    /**
     * @param list<PackageWorkspace> $successfulWorkspaces
     * @param array<string, string> $hashes
     */
    public function __construct(
        public array $successfulWorkspaces,
        public array $hashes,
        public int $failed,
    ) {
    }
}
