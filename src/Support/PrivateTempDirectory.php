<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Support;

use RuntimeException;

/**
 * Per-user working directory in a shared temp location, so other local users
 * cannot pre-create, poison or block package-manager caches and mutexes.
 */
final class PrivateTempDirectory
{
    public static function path(?string $parent = null): string
    {
        // shortcut: without ext-posix (e.g. Windows, per-user temp) the owner cannot be checked; add a probe-file check if needed there.
        $uid = function_exists('posix_geteuid') ? posix_geteuid() : null;
        $path = rtrim($parent ?? sys_get_temp_dir(), '/\\') . '/sympress-asset-compiler-' . ($uid ?? 'user');

        if (!is_dir($path) && !@mkdir($path, 0700) && !is_dir($path)) {
            throw new RuntimeException(sprintf('Cannot create private asset-compiler directory %s.', $path));
        }

        clearstatcache(true, $path);

        if (is_link($path) || !is_dir($path) || ($uid !== null && fileowner($path) !== $uid)) {
            throw new RuntimeException(sprintf('Refusing to use %s: it is a symlink or owned by another user.', $path));
        }

        if ($uid !== null && (fileperms($path) & 0077) !== 0 && !chmod($path, 0700)) {
            throw new RuntimeException(sprintf('Cannot restrict permissions of %s.', $path));
        }

        return $path;
    }
}
