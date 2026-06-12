<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Discovery;

final readonly class RootPackageSelection
{
    /** @param array<string, mixed>|null $override */
    public function __construct(
        public ?array $override,
        public bool $forceDefaults,
        public bool $disabled,
        public bool $explicit,
        public string $pattern,
    ) {
    }
}
