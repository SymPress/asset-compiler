<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\PackageManager;

final readonly class PackageManagerResolution
{
    public function __construct(
        public PackageManager $manager,
        public string $reason,
        public ?string $managerVersion = null,
        public ?string $nodeVersion = null,
    ) {
    }
}
