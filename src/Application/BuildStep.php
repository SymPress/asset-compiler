<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Application;

final readonly class BuildStep
{
    /**
     * @param list<string> $command
     * @param array<string, string|false> $environment
     */
    public function __construct(
        public string $label,
        public array $command,
        public string $workingDirectory,
        public int $timeout,
        public array $environment = [],
        public bool $parallel = true,
    ) {
    }

    public function displayCommand(): string
    {
        return implode(
            ' ',
            array_map(
                static fn (string $part): string => preg_match('/\s/', $part) ? escapeshellarg($part) : $part,
                $this->command,
            ),
        );
    }
}
