<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\PackageManager;

use SymPress\AssetCompiler\Discovery\PackageWorkspace;

final readonly class PackageManager
{
    public const NPM = 'npm';

    public const YARN = 'yarn';

    public const PNPM = 'pnpm';

    public function __construct(public string $name)
    {
    }

    /**
     * @return list<string>
     */
    public function installCommand(PackageWorkspace $workspace): array
    {
        return match ($this->name) {
            self::YARN => $this->hasLock($workspace, 'yarn.lock')
                ? ['yarn', 'install', '--frozen-lockfile']
                : ['yarn', 'install'],
            self::PNPM => $this->hasLock($workspace, 'pnpm-lock.yaml')
                ? ['pnpm', 'install', '--frozen-lockfile']
                : ['pnpm', 'install'],
            default => $this->hasNpmLock($workspace)
                ? ['npm', 'ci']
                : ['npm', 'install', '--no-package-lock'],
        };
    }

    /**
     * @return list<string>
     */
    public function updateCommand(PackageWorkspace $workspace): array
    {
        return match ($this->name) {
            self::YARN => ['yarn', 'upgrade'],
            self::PNPM => ['pnpm', 'update'],
            default => ['npm', 'update', '--no-save'],
        };
    }

    /**
     * @return list<string>
     */
    public function scriptCommand(string $script): array
    {
        [$name, $arguments] = $this->scriptParts($script);

        return match ($this->name) {
            self::YARN => array_merge(['yarn', $name], $arguments),
            self::PNPM => array_merge(['pnpm', 'run', $name], $arguments === [] ? [] : ['--'], $arguments),
            default => array_merge(['npm', 'run', $name], $arguments === [] ? [] : ['--'], $arguments),
        };
    }

    /**
     * @return array{string, list<string>}
     */
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

    /**
     * @return list<string>
     */
    private function splitArguments(string $arguments): array
    {
        $tokens = str_getcsv($arguments, ' ', '"', '\\');

        return array_values(
            array_filter(
                array_map(static fn (string $token): string => trim($token), $tokens),
                static fn (string $token): bool => $token !== '',
            ),
        );
    }

    private function hasNpmLock(PackageWorkspace $workspace): bool
    {
        return $this->hasLock($workspace, 'package-lock.json')
            || $this->hasLock($workspace, 'npm-shrinkwrap.json');
    }

    private function hasLock(PackageWorkspace $workspace, string $file): bool
    {
        return is_file(rtrim($workspace->path, '/') . '/' . $file);
    }
}
