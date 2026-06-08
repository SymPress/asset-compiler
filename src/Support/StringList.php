<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Support;

final class StringList
{
    /**
     * @return list<string>
     */
    public static function fromCsv(string $value): array
    {
        return $value
            |> trim(...)
            |> (static fn(string $value): array => $value === '' ? [] : explode(',', $value))
            |> self::trimmedNonEmpty(...);
    }

    /**
     * @return list<string>
     */
    public static function fromShellArguments(string $arguments): array
    {
        return $arguments
            |> (static fn(string $value): array => str_getcsv(
                $value,
                separator: ' ',
                enclosure: '"',
                escape: '\\',
            ))
            |> self::stringsOnly(...)
            |> self::trimmedNonEmpty(...);
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    public static function trimmedNonEmpty(array $values): array
    {
        return $values
            |> (static fn(array $values): array => array_map(
                static fn(string $value): string => trim($value),
                $values,
            ))
            |> (static fn(array $values): array => array_filter(
                $values,
                static fn(string $value): bool => $value !== '',
            ))
            |> array_values(...);
    }

    /**
     * @param list<string|null> $values
     * @return list<string>
     */
    private static function stringsOnly(array $values): array
    {
        return array_values(array_filter($values, is_string(...)));
    }
}
