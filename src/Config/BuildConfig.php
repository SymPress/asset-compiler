<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Config;

final readonly class BuildConfig
{
    /**
     * @param list<string> $scripts
     * @param array<string, string|false> $env
     * @param list<string> $sourcePaths
     */
    public function __construct(
        public array $scripts,
        public DependencyMode $dependencyMode,
        public ?string $packageManager,
        public array $env,
        public array $sourcePaths,
        public int $timeout,
    ) {
    }

    public function runnable(): bool
    {
        return $this->scripts !== [] || $this->dependencyMode !== DependencyMode::None;
    }
}
