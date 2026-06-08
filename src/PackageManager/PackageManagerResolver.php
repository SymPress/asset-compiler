<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\PackageManager;

use Symfony\Component\Process\ExecutableFinder;
use SymPress\AssetCompiler\Discovery\PackageWorkspace;

final class PackageManagerResolver
{
    private ExecutableFinder $executables;

    /**
     * @var array<string, bool>
     */
    private array $available = [];

    public function __construct()
    {
        $this->executables = new ExecutableFinder();
    }

    public function resolve(PackageWorkspace $workspace): PackageManager
    {
        $candidates = array_values(
            array_unique(
                array_filter(
                    [
                        $workspace->build->packageManager,
                        $this->fromPackageJson($workspace),
                        $this->fromLockFiles($workspace),
                        PackageManager::NPM,
                    ],
                    static fn (?string $name): bool => is_string($name) && $name !== '',
                ),
            ),
        );

        foreach ($candidates as $candidate) {
            $name = $this->normalize($candidate);

            if ($name !== null && $this->isAvailable($name)) {
                return new PackageManager($name);
            }
        }

        throw new \RuntimeException(
            sprintf(
                'No supported package manager found for %s. Install npm, yarn, or pnpm.',
                $workspace->name,
            ),
        );
    }

    private function fromPackageJson(PackageWorkspace $workspace): ?string
    {
        $manager = $workspace->packageJson['packageManager'] ?? null;

        if (!is_string($manager) || trim($manager) === '') {
            return null;
        }

        return explode('@', trim($manager), 2)[0] ?? null;
    }

    private function fromLockFiles(PackageWorkspace $workspace): ?string
    {
        $path = rtrim($workspace->path, '/');

        if (is_file($path . '/pnpm-lock.yaml')) {
            return PackageManager::PNPM;
        }

        if (is_file($path . '/yarn.lock') && !is_file($path . '/package-lock.json')) {
            return PackageManager::YARN;
        }

        if (is_file($path . '/package-lock.json') || is_file($path . '/npm-shrinkwrap.json')) {
            return PackageManager::NPM;
        }

        return null;
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

        $this->available[$name] = is_string($this->executables->find($name));

        return $this->available[$name];
    }
}
