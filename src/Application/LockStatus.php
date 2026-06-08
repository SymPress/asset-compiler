<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Application;

final readonly class LockStatus
{
    public function __construct(
        public bool $fresh,
        public string $reason,
    ) {
    }
}
