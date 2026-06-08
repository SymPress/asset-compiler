<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Tests\Support;

use PHPUnit\Framework\TestCase;
use SymPress\AssetCompiler\Support\StringList;

final class StringListTest extends TestCase
{
    public function testCreatesTrimmedCsvList(): void
    {
        self::assertSame(
            ['vendor/package', 'vendor/theme-*'],
            StringList::fromCsv(' vendor/package, , vendor/theme-* '),
        );
    }

    public function testSplitsShellArguments(): void
    {
        self::assertSame(
            ['--mode', 'production build', '--flag'],
            StringList::fromShellArguments('--mode "production build" --flag'),
        );
    }
}
