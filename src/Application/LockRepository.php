<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Application;

use Composer\IO\IOInterface;
use Symfony\Component\Filesystem\Filesystem;
use SymPress\AssetCompiler\Discovery\PackageWorkspace;

final readonly class LockRepository
{
    private const FILE = '.sympress_asset_compiler.lock';

    public function __construct(
        private Filesystem $filesystem,
        private IOInterface $io,
    ) {
    }

    public function isFresh(PackageWorkspace $workspace, string $hash, string $ignoreLock): bool
    {
        if ($this->shouldIgnore($workspace->name, $ignoreLock)) {
            return false;
        }

        $file = $this->file($workspace);

        if (!is_file($file)) {
            return false;
        }

        $contents = file_get_contents($file);

        return is_string($contents) && trim($contents) === $hash;
    }

    public function write(PackageWorkspace $workspace, string $hash): void
    {
        $file = $this->file($workspace);

        try {
            $this->filesystem->dumpFile($file, $hash . PHP_EOL);
        } catch (\Throwable $throwable) {
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

        foreach (explode(',', $ignoreLock) as $pattern) {
            $pattern = trim($pattern);

            if ($pattern === '') {
                continue;
            }

            if ($pattern === $packageName || fnmatch($pattern, $packageName, FNM_PATHNAME | FNM_PERIOD | FNM_CASEFOLD)) {
                return true;
            }
        }

        return false;
    }
}
