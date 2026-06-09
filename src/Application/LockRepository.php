<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Application;

use Composer\IO\IOInterface;
use Symfony\Component\Filesystem\Filesystem;
use SymPress\AssetCompiler\Discovery\PackageWorkspace;
use SymPress\AssetCompiler\Support\StringList;
use Throwable;

final readonly class LockRepository
{
    private const string FILE = '.sympress_asset_compiler.lock';

    public function __construct(
        private Filesystem $filesystem,
        private IOInterface $io,
    ) {
    }

    public function isFresh(PackageWorkspace $workspace, string $hash, string $ignoreLock): bool
    {
        return $this->status($workspace, $hash, $ignoreLock)->fresh;
    }

    public function status(PackageWorkspace $workspace, string $hash, string $ignoreLock): LockStatus
    {
        if ($this->shouldIgnore($workspace->name, $ignoreLock)) {
            return new LockStatus(false, 'ignored by option');
        }

        $file = $this->file($workspace);

        if (!is_file($file)) {
            return new LockStatus(false, 'missing lock');
        }

        $contents = file_get_contents($file);

        if (!is_string($contents) || trim($contents) === '') {
            return new LockStatus(false, 'empty lock');
        }

        return trim($contents) === $hash
            ? new LockStatus(true, 'current')
            : new LockStatus(false, 'stale lock');
    }

    public function write(PackageWorkspace $workspace, string $hash): void
    {
        $file = $this->file($workspace);

        try {
            $this->filesystem->dumpFile($file, $hash . PHP_EOL);
        } catch (Throwable $throwable) {
            $this->io->writeError(
                sprintf('Could not write asset compiler lock for %s: %s', $workspace->name, $throwable->getMessage()),
                true,
                IOInterface::VERBOSE,
            );
        }
    }

    private function file(PackageWorkspace $workspace): string
    {
        return rtrim($workspace->path, '/') . '/' . self::FILE;
    }

    private function shouldIgnore(string $packageName, string $ignoreLock): bool
    {
        $ignoreLock = trim($ignoreLock);

        if ($ignoreLock === '') {
            return false;
        }

        if ($ignoreLock === '*') {
            return true;
        }

        return array_any(
            StringList::fromCsv($ignoreLock),
            static fn(string $pattern): bool => $pattern === $packageName
                || fnmatch($pattern, $packageName, FNM_PATHNAME | FNM_PERIOD | FNM_CASEFOLD),
        );
    }
}
