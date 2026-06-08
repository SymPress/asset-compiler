<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Config;

final readonly class BuildConfig
{
    /**
     * @param list<string> $scripts
     * @param array<string, string|false> $env
     * @param list<string> $sourcePaths
     * @param list<PrecompiledAssetConfig> $precompiledAssets
     */
    public function __construct(
        public array $scripts,
        public DependencyMode $dependencyMode,
        public ?string $packageManager,
        public ?string $packageManagerPreference,
        public array $env,
        public array $sourcePaths,
        public int $timeout,
        public bool $isolatedCache = false,
        public array $precompiledAssets = [],
    ) {
    }

    public function runnable(): bool
    {
        return $this->scripts !== [] || $this->dependencyMode !== DependencyMode::None || $this->precompiledAssets !== [];
    }
}
