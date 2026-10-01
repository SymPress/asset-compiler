<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\PackageManager;

use RuntimeException;
use SymPress\AssetCompiler\Discovery\PackageWorkspace;
use SymPress\AssetCompiler\Support\StringList;

final readonly class PackageManager
{
    private const string YARN_MUTEX = 'file:/tmp/sympress-asset-compiler-yarn.lock';

    public const string NPM = 'npm';

    public const string YARN = 'yarn';

    public const string PNPM = 'pnpm';

    public function __construct(public string $name, public ?string $version = null)
    {
    }

    /** @return list<string> */
    public function installCommand(PackageWorkspace $workspace, ?string $cacheDirectory = null): array
    {
        $locked = match ($this->name) {
            self::YARN => $this->hasLock($workspace, 'yarn.lock'),
            self::PNPM => $this->hasLock($workspace, 'pnpm-lock.yaml'),
            default => $this->hasNpmLock($workspace),
        };
        if ($workspace->build->production && !$locked) {
            throw new RuntimeException('Production dependency installation requires a package-manager lockfile.');
        }

        $modernYarn = $this->modernYarn($workspace);
        $command = match ($this->name) {
            self::YARN => $modernYarn
                ? ['env', 'YARN_ENABLE_SCRIPTS=' . ($workspace->build->allowLifecycleScripts ? 'true' : 'false'), 'yarn', 'install', '--immutable']
                : array_merge(['yarn', 'install'], $locked ? ['--frozen-lockfile'] : [], ['--mutex', self::YARN_MUTEX]),
            self::PNPM => array_merge(['pnpm', 'install'], $locked ? ['--frozen-lockfile'] : []),
            default => $locked ? ['npm', 'ci'] : ['npm', 'install', '--no-package-lock'],
        };
        if (!$workspace->build->allowLifecycleScripts) {
            $command[] = $modernYarn ? $this->yarnSkipBuildFlag($workspace) : '--ignore-scripts';
        }
        if ($modernYarn && $cacheDirectory !== null) {
            throw new RuntimeException('Modern Yarn isolated-cache flags are unsupported; configure its cache explicitly.');
        }

        return $this->withCache($command, $cacheDirectory);
    }

    public function modernYarn(PackageWorkspace $workspace): bool
    {
        if ($this->name !== self::YARN) {
            return false;
        }
        $version = $this->version;
        if ($version === null) {
            $declared = $workspace->packageJson['packageManager'] ?? null;
            $version = is_string($declared) && str_starts_with($declared, 'yarn@') ? substr($declared, 5) : null;
        }
        if ($version === null || preg_match('/^v?(\d+)\./', $version, $matches) !== 1) {
            throw new RuntimeException('A verified Yarn version is required to choose safe installation flags.');
        }

        return (int) $matches[1] >= 2;
    }

    public function yarnSkipBuildFlag(PackageWorkspace $workspace): string
    {
        $version = $this->version ?? (is_string($workspace->packageJson['packageManager'] ?? null) ? substr($workspace->packageJson['packageManager'], 5) : '');

        return str_starts_with(ltrim($version, 'v'), '2.') ? '--skip-builds' : '--mode=skip-build';
    }

    /** @return list<string> */
    public function updateCommand(PackageWorkspace $workspace, ?string $cacheDirectory = null): array
    {
        if ($workspace->build->production) {
            throw new RuntimeException('Production builds cannot update dependencies.');
        }
        $modernYarn = $this->modernYarn($workspace);
        if ($modernYarn && !$workspace->build->allowLifecycleScripts && $this->yarnSkipBuildFlag($workspace) === '--skip-builds') {
            throw new RuntimeException('Yarn 2 dependency updates without lifecycle scripts are unsupported.');
        }
        $command = match ($this->name) {
            self::YARN => $modernYarn
                ? ['env', 'YARN_ENABLE_SCRIPTS=' . ($workspace->build->allowLifecycleScripts ? 'true' : 'false'), 'yarn', 'up']
                : ['yarn', 'upgrade', '--mutex', self::YARN_MUTEX],
            self::PNPM => ['pnpm', 'update'],
            default => ['npm', 'update', '--no-save'],
        };
        if (!$workspace->build->allowLifecycleScripts) {
            $command[] = $modernYarn ? $this->yarnSkipBuildFlag($workspace) : '--ignore-scripts';
        }
        if ($modernYarn && $cacheDirectory !== null) {
            throw new RuntimeException('Modern Yarn isolated-cache flags are unsupported; configure its cache explicitly.');
        }

        return $this->withCache($command, $cacheDirectory);
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
