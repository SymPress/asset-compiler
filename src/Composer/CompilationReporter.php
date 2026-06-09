<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Composer;

use Composer\IO\IOInterface;
use SymPress\AssetCompiler\Application\CompilationResult;

final class CompilationReporter
{
    private function __construct()
    {
    }

    public static function write(IOInterface $io, CompilationResult $result, bool $dryRun = false): void
    {
        if ($result->total === 0) {
            $io->write('<comment>No asset packages found.</comment>');

            return;
        }

        $io->write(
            sprintf(
                '<info>%s:</info> %d discovered, %d %s, %d current, %d failed.',
                $dryRun ? 'Asset compilation plan' : 'Asset compilation finished',
                $result->total,
                $result->successfulTasks,
                $dryRun ? 'planned' : 'built',
                $result->skipped,
                $result->failed,
            ),
        );

        if (!$dryRun && $result->failed === 0 && $result->skipped === $result->total) {
            $io->write('<comment>All discovered asset packages are already current.</comment>');
        }
    }
}
