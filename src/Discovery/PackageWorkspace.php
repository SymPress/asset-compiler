<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Discovery;

use SymPress\AssetCompiler\Config\BuildConfig;

final readonly class PackageWorkspace
{
    /** @param array<string, mixed> $packageJson */
    public function __construct(
        public string $name,
        public string $type,
        public string $path,
        public BuildConfig $build,
        public array $packageJson,
        public string $version = '',
        public string $reference = '',
        public string $stability = 'stable',
    ) {
    }
}
