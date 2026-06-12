<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Config;

final readonly class PrecompiledAssetConfig
{
    /** @param array<string, mixed> $config */
    public function __construct(
        public string $adapter,
        public string $source,
        public string $target,
        public array $config = [],
        public ?string $stability = null,
        public ?string $checksum = null,
    ) {
    }
}
