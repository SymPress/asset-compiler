<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Config;

use InvalidArgumentException;

final readonly class RootConfig
{
    public const string EXTRA_KEY = 'sympress.asset-compiler';

    public const string NESTED_EXTRA_KEY = 'asset-compiler';

    public const string LEGACY_EXTRA_KEY = 'composer-asset-compiler';

    public const string EXECUTION_STRATEGY_STAGED = 'staged';

    public const string EXECUTION_STRATEGY_GROUPED = 'grouped';

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
        public bool $isolatedCache,
        public bool $wipeNodeModules,
        public int $timeoutIncrement,
        public ?string $packageManager,
        public array $defaults,
        public array $packages,
        public array $packageTypes,
        public array $env,
        public bool $allowPackageConfigFiles = false,
        public bool $requirePrecompiledChecksum = false,
        public bool $clearPackageManagerCache = false,
        public string $executionStrategy = self::EXECUTION_STRATEGY_STAGED,
    ) {
    }

    public static function normalizeExecutionStrategy(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '') {
            return self::EXECUTION_STRATEGY_STAGED;
        }

        $value = strtolower(trim($value));

        return match ($value) {
            self::EXECUTION_STRATEGY_STAGED => self::EXECUTION_STRATEGY_STAGED,
            self::EXECUTION_STRATEGY_GROUPED, 'pipeline', 'package-pipeline' => self::EXECUTION_STRATEGY_GROUPED,
            default => throw new InvalidArgumentException(sprintf(
                'Unsupported asset compiler execution-strategy "%s". Supported values are staged and grouped.',
                $value,
            )),
        };
    }

    public function withRuntimeOverrides(
        ?int $maxProcesses = null,
        ?bool $wipeNodeModules = null,
        ?bool $clearPackageManagerCache = null,
        ?string $executionStrategy = null,
    ): self {

        return new self(
            rootPath: $this->rootPath,
            autoRun: $this->autoRun,
            autoDiscover: $this->autoDiscover,
            stopOnFailure: $this->stopOnFailure,
            maxProcesses: max(1, min(8, $maxProcesses ?? $this->maxProcesses)),
            processPoll: $this->processPoll,
            isolatedCache: $this->isolatedCache,
            wipeNodeModules: $wipeNodeModules ?? $this->wipeNodeModules,
            timeoutIncrement: $this->timeoutIncrement,
            packageManager: $this->packageManager,
            defaults: $this->defaults,
            packages: $this->packages,
            packageTypes: $this->packageTypes,
            env: $this->env,
            allowPackageConfigFiles: $this->allowPackageConfigFiles,
            requirePrecompiledChecksum: $this->requirePrecompiledChecksum,
            clearPackageManagerCache: $clearPackageManagerCache ?? $this->clearPackageManagerCache,
            executionStrategy: $executionStrategy === null
                ? $this->executionStrategy
                : self::normalizeExecutionStrategy($executionStrategy),
        );
    }
}
