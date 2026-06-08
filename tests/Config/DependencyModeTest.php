<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Tests\Config;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SymPress\AssetCompiler\Config\DependencyMode;

final class DependencyModeTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, DependencyMode}>
     */
    public static function values(): iterable
    {
        yield 'install' => ['install', DependencyMode::Install];
        yield 'update' => ['UPDATE', DependencyMode::Update];
        yield 'none' => [' none ', DependencyMode::None];
        yield 'unknown uses default' => ['missing', DependencyMode::Install];
        yield 'empty uses default' => ['', DependencyMode::Install];
        yield 'non string uses default' => [true, DependencyMode::Install];
    }

    #[DataProvider('values')]
    public function testCreatesModeFromMixedValue(mixed $value, DependencyMode $expected): void
    {
        self::assertSame($expected, DependencyMode::fromMixed($value, DependencyMode::Install));
    }
}
