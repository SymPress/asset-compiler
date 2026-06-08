<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Support;

use JsonException;
use RuntimeException;

final class JsonData
{
    public static function decode(string $json, string $context): mixed
    {
        try {
            return json_decode($json, associative: true, flags: JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                sprintf('Could not deserialize JSON for %s: %s.', $context, $exception->getMessage()),
                previous: $exception,
            );
        }
    }

    public static function decodeFile(string $file, string $context, bool $allowEmpty = false): mixed
    {
        $json = self::readFile($file, $context);

        if (trim($json) === '') {
            if ($allowEmpty) {
                return [];
            }

            throw new RuntimeException(sprintf('Could not deserialize JSON for %s: file is empty.', $context));
        }

        return self::decode($json, $context);
    }

    /**
     * @return array<string, mixed>
     */
    public static function decodeObject(string $json, string $context): array
    {
        if (!str_starts_with(ltrim($json), '{')) {
            self::decode($json, $context);

            throw new RuntimeException(sprintf('Expected JSON object for %s.', $context));
        }

        return self::decode($json, $context)
            |> (static fn(mixed $decoded): array => self::object($decoded, $context));
    }

    /**
     * @return array<string, mixed>
     */
    public static function decodeObjectFile(string $file, string $context, bool $allowEmpty = false): array
    {
        $json = self::readFile($file, $context);

        if (trim($json) === '') {
            if ($allowEmpty) {
                return [];
            }

            throw new RuntimeException(sprintf('Could not deserialize JSON for %s: file is empty.', $context));
        }

        return self::decodeObject($json, $context);
    }

    /**
     * @return array<string, mixed>
     */
    public static function object(mixed $value, string $context): array
    {
        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new RuntimeException(sprintf('Expected JSON object for %s.', $context));
        }

        return self::stringKeyedArray($value);
    }

    public static function encode(mixed $value, string $context): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                sprintf('Could not serialize JSON for %s: %s.', $context, $exception->getMessage()),
                previous: $exception,
            );
        }
    }

    /**
     * @param array<array-key, mixed> $value
     * @return array<string, mixed>
     */
    public static function stringKeyedArray(array $value): array
    {
        $result = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    private static function readFile(string $file, string $context): string
    {
        $contents = file_get_contents($file);

        if (!is_string($contents)) {
            throw new RuntimeException(sprintf('Could not read JSON for %s.', $context));
        }

        return $contents;
    }
}
