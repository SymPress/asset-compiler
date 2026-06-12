<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\PackageManager;

use SymPress\AssetCompiler\Discovery\PackageWorkspace;
use SymPress\AssetCompiler\Support\StringList;

final readonly class PackageManager
{
    private const string YARN_MUTEX = 'file:/tmp/sympress-asset-compiler-yarn.lock';

    public const string NPM = 'npm';

    public const string YARN = 'yarn';

    public const string PNPM = 'pnpm';

    public function __construct(public string $name)
    {
    }

    /** @return list<string> */
    public function installCommand(PackageWorkspace $workspace, ?string $cacheDirectory = null): array
    {
        return $this->withCache(match ($this->name) {
            self::YARN => $this->hasLock($workspace, 'yarn.lock')
                ? ['yarn', 'install', '--frozen-lockfile', '--mutex', self::YARN_MUTEX]
                : ['yarn', 'install', '--mutex', self::YARN_MUTEX],
            self::PNPM => $this->hasLock($workspace, 'pnpm-lock.yaml')
                ? ['pnpm', 'install', '--frozen-lockfile']
                : ['pnpm', 'install'],
            default => $this->hasNpmLock($workspace)
                ? ['npm', 'ci']
                : ['npm', 'install', '--no-package-lock'],
        }, $cacheDirectory);
    }

    /** @return list<string> */
    public function updateCommand(PackageWorkspace $workspace, ?string $cacheDirectory = null): array
    {
        return $this->withCache(match ($this->name) {
            self::YARN => ['yarn', 'upgrade', '--mutex', self::YARN_MUTEX],
            self::PNPM => ['pnpm', 'update'],
            default => ['npm', 'update', '--no-save'],
        }, $cacheDirectory);
    }

    /** @return list<string> */
    public function scriptCommand(string $script): array
    {
        [$name, $arguments] = $this->scriptParts($script);

        return match ($this->name) {
            self::YARN => array_merge(['yarn', $name], $arguments),
            self::PNPM => array_merge(['pnpm', 'run', $name], $arguments === [] ? [] : ['--'], $arguments),
            default => array_merge(['npm', 'run', $name], $arguments === [] ? [] : ['--'], $arguments),
        };
    }

    /** @return array{string, list<string>} */
    private function scriptParts(string $script): array
    {
        $parts = explode(' -- ', $script, 2);
        $name = trim($parts[0]);
        $arguments = [];

        if (isset($parts[1]) && trim($parts[1]) !== '') {
            $arguments = $this->splitArguments(trim($parts[1]));
        }

        return [$name, $arguments];
    }

    /** @return list<string> */
    private function splitArguments(string $arguments): array
    {
        return StringList::fromShellArguments($arguments);
    }

    private function hasNpmLock(PackageWorkspace $workspace): bool
    {
        return $this->hasLock($workspace, 'package-lock.json')
            || $this->hasLock($workspace, 'npm-shrinkwrap.json');
    }

    /**
     * @param list<string> $command
     * @return list<string>
     */
    private function withCache(array $command, ?string $cacheDirectory): array
    {
        if ($cacheDirectory === null || trim($cacheDirectory) === '') {
            return $command;
        }

        return match ($this->name) {
            self::YARN => array_merge($command, ['--cache-folder', $cacheDirectory]),
            self::PNPM => array_merge($command, ['--store-dir', $cacheDirectory]),
            default => array_merge($command, ['--cache', $cacheDirectory]),
        };
    }

    private function hasLock(PackageWorkspace $workspace, string $file): bool
    {
        return is_file(rtrim($workspace->path, '/') . '/' . $file);
    }
}
