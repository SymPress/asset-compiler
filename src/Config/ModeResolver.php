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
        [$found, $modeConfig] = $this->selectedMode($data);

        if (!$found || !is_array($modeConfig)) {
            unset($data['$mode'], $data['env']);

            return $data;
        }

        unset($data['$mode'], $data['env']);

        /** @var array<string, mixed> $resolved */
        $resolved = array_replace_recursive($data, self::stringKeyedArray($modeConfig));

        return $resolved;
    }

    public function property(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        [$found, $modeConfig] = $this->selectedMode($value);

        if (!$found) {
            unset($value['$mode'], $value['env']);

            return $value;
        }

        return $modeConfig;
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array{bool, mixed}
     */
    private function selectedMode(array $data): array
    {
        $modes = $data['$mode'] ?? $data['env'] ?? null;

        if (!is_array($modes)) {
            return [false, null];
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
            if (isset($modes[$candidate])) {
                return [true, $modes[$candidate]];
            }
        }

        return [false, null];
    }

    /**
     * @param array<array-key, mixed> $value
     * @return array<string, mixed>
     */
    private static function stringKeyedArray(array $value): array
    {
        $result = [];

        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                continue;
            }

            $result[$key] = $item;
        }

        return $result;
    }
}
