<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Application;

final readonly class CompilationResult
{
    public bool $successful;

    public function __construct(
        public int $total,
        public int $successfulTasks,
        public int $skipped,
        public int $failed,
    ) {

        $this->successful = $failed === 0;
    }
}
