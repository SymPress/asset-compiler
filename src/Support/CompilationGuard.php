<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Support;

use RuntimeException;

final class CompilationGuard
{
    public const string ENVIRONMENT_VARIABLE = 'SYMPRESS_ASSET_COMPILER_ACTIVE';

    public static function active(): bool
    {
        $value = getenv(self::ENVIRONMENT_VARIABLE);

        return $value !== false && $value !== '';
    }

    public static function assertAllowed(): void
    {
        if (self::active()) {
            throw new RuntimeException('Nested asset compilation is not allowed from an asset build process. Disable deployment orchestrators in the root packages selection.');
        }
    }
}
