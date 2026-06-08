<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Config;

enum DependencyMode: string
{
    case Install = 'install';
    case Update = 'update';
    case None = 'none';

    public static function fromMixed(mixed $value, self $default): self
    {
        if (!is_string($value) || trim($value) === '') {
            return $default;
        }

        return self::tryFrom(strtolower(trim($value))) ?? $default;
    }
}
