<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Config;

final readonly class RootConfig
{
    public const EXTRA_KEY = 'sympress.asset-compiler';

    public const NESTED_EXTRA_KEY = 'asset-compiler';

    public const LEGACY_EXTRA_KEY = 'composer-asset-compiler';

    /**
     * @param array<string, mixed> $defaults
     * @param array<string, mixed> $packages
     * @param list<string> $packageTypes
     * @param array<string, string|false> $env
     */
    public function __construct(
        public string $rootPath,
        public bool $autoRun,
        public bool $autoDiscover,
        public bool $stopOnFailure,
        public int $maxProcesses,
        public int $processPoll,
        public ?string $packageManager,
        public array $defaults,
        public array $packages,
        public array $packageTypes,
        public array $env,
    ) {
    }
}
