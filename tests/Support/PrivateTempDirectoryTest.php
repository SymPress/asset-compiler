<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Tests\Support;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SymPress\AssetCompiler\Support\PrivateTempDirectory;

final class PrivateTempDirectoryTest extends TestCase
{
    private string $parent;

    protected function setUp(): void
    {
        $this->parent = sys_get_temp_dir() . '/sympress-private-dir-test-' . bin2hex(random_bytes(4));
        mkdir($this->parent, 0777);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->parent . '/*') ?: [] as $entry) {
            is_link($entry) || is_file($entry) ? unlink($entry) : rmdir($entry);
        }
        rmdir($this->parent);
    }

    public function testCreatesPerUserDirectoryReadableOnlyByOwner(): void
    {
        $path = PrivateTempDirectory::path($this->parent);

        self::assertStringStartsWith($this->parent . '/sympress-asset-compiler-', $path);
        self::assertDirectoryExists($path);
        self::assertSame(0700, fileperms($path) & 0777);
        self::assertSame($path, PrivateTempDirectory::path($this->parent));
    }

    public function testTightensLoosePermissionsOfOwnDirectory(): void
    {
        $path = PrivateTempDirectory::path($this->parent);
        chmod($path, 0777);

        PrivateTempDirectory::path($this->parent);

        clearstatcache(true, $path);
        self::assertSame(0700, fileperms($path) & 0777);
    }

    public function testRejectsPreCreatedSymlink(): void
    {
        $path = PrivateTempDirectory::path($this->parent);
        rmdir($path);
        mkdir($this->parent . '/elsewhere', 0777);
        symlink($this->parent . '/elsewhere', $path);

        $this->expectException(RuntimeException::class);
        PrivateTempDirectory::path($this->parent);
    }
}
