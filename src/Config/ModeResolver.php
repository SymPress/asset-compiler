<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Config;

final readonly class ModeResolver
{
    public function __construct(
        private ?string $mode,
        private bool $devMode,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function root(array $data): array
    {
        $modeConfig = $this->modeConfig($data);

        if ($modeConfig === null) {
            unset($data['$mode'], $data['env']);

            return $data;
        }

        unset($data['$mode'], $data['env']);

        return array_replace_recursive($data, $modeConfig);
    }

    public function property(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $modeConfig = $this->modeConfig($value);

        if ($modeConfig === null) {
            unset($value['$mode'], $value['env']);

            return $value;
        }

        return $modeConfig;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    private function modeConfig(array $data): ?array
    {
        $modes = $data['$mode'] ?? $data['env'] ?? null;

        if (!is_array($modes)) {
            return null;
        }

        $candidates = array_filter(
            [
                $this->mode,
                $this->devMode ? null : '$default-no-dev',
                '$default',
            ],
            static fn (?string $mode): bool => is_string($mode) && $mode !== '',
        );

        foreach ($candidates as $candidate) {
            $selected = $modes[$candidate] ?? null;

            if (is_array($selected)) {
                return $selected;
            }
        }

        return null;
    }
}
