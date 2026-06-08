<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Tests\Config;

use PHPUnit\Framework\TestCase;
use SymPress\AssetCompiler\Config\ModeResolver;

final class ModeResolverTest extends TestCase
{
    public function testResolvesScalarPropertyMode(): void
    {
        $resolver = new ModeResolver('production', true);

        self::assertSame(
            'build:production',
            $resolver->property([
                '$mode' => [
                    '$default' => 'build',
                    'production' => 'build:production',
                ],
            ]),
        );
    }

    public function testResolvesListPropertyMode(): void
    {
        $resolver = new ModeResolver('production', true);

        self::assertSame(
            ['build', 'build:admin'],
            $resolver->property([
                '$mode' => [
                    'production' => ['build', 'build:admin'],
                ],
            ]),
        );
    }
}
