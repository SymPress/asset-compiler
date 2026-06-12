<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\PackageManager;

use Closure;
use RuntimeException;
use SymPress\AssetCompiler\Discovery\PackageWorkspace;
use Symfony\Component\Process\ExecutableFinder;

final class PackageManagerResolver
{
    private ExecutableFinder $executables;

    private ?Closure $availability;

    /** @var array<string, bool> */
    private array $available = [];

    /** @param callable(string): bool|null $availability */
    public function __construct(?callable $availability = null)
    {
        $this->executables = new ExecutableFinder();
        $this->availability = $availability !== null ? Closure::fromCallable($availability) : null;
    }

    public function resolve(PackageWorkspace $workspace): PackageManagerResolution
    {
        $candidates = $this->candidates($workspace);
        $seen = [];

        foreach ($candidates as [$candidate, $reason]) {
            $name = $this->normalize($candidate ?? '');

            if ($name === null || isset($seen[$name])) {
                continue;
            }

            $seen[$name] = true;

            if ($this->isAvailable($name)) {
                return new PackageManagerResolution(new PackageManager($name), $reason);
            }
        }

        throw new RuntimeException(
            sprintf(
                'No supported package manager found for %s. Install npm, yarn, or pnpm.',
                $workspace->name,
            ),
        );
    }

    /** @return list<array{?string, string}> */
    private function candidates(PackageWorkspace $workspace): array
    {
        return [
            [$workspace->build->packageManager, 'package config'],
            [$this->fromPackageJson($workspace), 'package.json packageManager'],
            [$this->fromLockFiles($workspace), 'lock file'],
            [$workspace->build->packageManagerPreference, 'root preference'],
            [PackageManager::NPM, 'npm fallback'],
        ];
    }

    private function fromPackageJson(PackageWorkspace $workspace): ?string
    {
        $manager = $workspace->packageJson['packageManager'] ?? null;

        if (!is_string($manager) || trim($manager) === '') {
            return null;
        }

        return explode('@', trim($manager), 2)[0];
    }

    private function fromLockFiles(PackageWorkspace $workspace): ?string
    {
        $path = rtrim($workspace->path, '/');
        $managers = [];

        if (is_file($path . '/pnpm-lock.yaml')) {
            $managers[] = PackageManager::PNPM;
        }

        if (is_file($path . '/yarn.lock')) {
            $managers[] = PackageManager::YARN;
        }

        if (is_file($path . '/package-lock.json') || is_file($path . '/npm-shrinkwrap.json')) {
            $managers[] = PackageManager::NPM;
        }

        $managers = array_values(array_unique($managers));

        return count($managers) === 1 ? array_first($managers) : null;
    }

    private function normalize(string $name): ?string
    {
        $name = strtolower(trim($name));

        return match ($name) {
            PackageManager::NPM, PackageManager::YARN, PackageManager::PNPM => $name,
            default => null,
        };
    }

    private function isAvailable(string $name): bool
    {
        if (array_key_exists($name, $this->available)) {
            return $this->available[$name];
        }

        $this->available[$name] = $this->availability !== null
            ? (bool) ($this->availability)($name)
            : is_string($this->executables->find($name));

        return $this->available[$name];
    }
}
