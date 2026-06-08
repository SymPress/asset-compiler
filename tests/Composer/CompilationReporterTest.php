<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Tests\Composer;

use Composer\IO\BufferIO;
use PHPUnit\Framework\TestCase;
use SymPress\AssetCompiler\Application\CompilationResult;
use SymPress\AssetCompiler\Composer\CompilationReporter;

final class CompilationReporterTest extends TestCase
{
    public function testReportsFinishedCompilation(): void
    {
        $io = new BufferIO();

        CompilationReporter::write($io, new CompilationResult(8, 3, 5, 0));

        self::assertStringContainsString(
            'Asset compilation finished: 8 discovered, 3 built, 5 current, 0 failed.',
            $io->getOutput(),
        );
    }

    public function testReportsCurrentPackages(): void
    {
        $io = new BufferIO();

        CompilationReporter::write($io, new CompilationResult(8, 0, 8, 0));

        self::assertStringContainsString(
            'All discovered asset packages are already current.',
            $io->getOutput(),
        );
    }

    public function testReportsDryRunPlan(): void
    {
        $io = new BufferIO();

        CompilationReporter::write($io, new CompilationResult(8, 8, 0, 0), true);

        self::assertStringContainsString(
            'Asset compilation plan: 8 discovered, 8 planned, 0 current, 0 failed.',
            $io->getOutput(),
        );
    }
}
