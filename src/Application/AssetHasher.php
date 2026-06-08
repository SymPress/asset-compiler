<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Application;

use Composer\IO\IOInterface;
use Symfony\Component\Finder\Finder;
use SymPress\AssetCompiler\Discovery\PackageWorkspace;

final readonly class AssetHasher
{
    /**
     * @var list<string>
     */
    private const DEFAULT_FILES = [
        'composer.json',
        'package.json',
        'package-lock.json',
        'npm-shrinkwrap.json',
        'pnpm-lock.yaml',
        'yarn.lock',
        'webpack.config.js',
        'webpack.config.cjs',
        'webpack.config.mjs',
        'vite.config.js',
        'vite.config.ts',
        'postcss.config.js',
        'postcss.config.cjs',
        'tailwind.config.js',
        'tailwind.config.cjs',
        'tsconfig.json',
        'babel.config.js',
    ];

    /**
     * @var list<string>
     */
    private const DEFAULT_DIRECTORIES = [
        'resources',
        'frontend',
        'client',
        'assets-src',
    ];

    public function __construct(private IOInterface $io)
    {
    }

    public function hash(PackageWorkspace $workspace): string
    {
        $files = $this->files($workspace);
        $context = [
            'package' => $workspace->name,
            'manager' => $workspace->build->packageManager,
            'manager-preference' => $workspace->build->packageManagerPreference,
            'dependencies' => $workspace->build->dependencyMode->value,
            'scripts' => $workspace->build->scripts,
            'env' => $workspace->build->env,
            'precompiled' => array_map(
                static fn (\SymPress\AssetCompiler\Config\PrecompiledAssetConfig $config): array => [
                    'adapter' => $config->adapter,
                    'source' => $config->source,
                    'target' => $config->target,
                    'config' => $config->config,
                    'stability' => $config->stability,
                ],
                $workspace->build->precompiledAssets,
            ),
        ];

        $payload = hash('sha256', json_encode($context, JSON_THROW_ON_ERROR));

        foreach ($files as $file) {
            $payload .= hash('sha256', $this->relativePath($workspace, $file));
            $payload .= is_file($file) ? hash_file('sha256', $file) : '';
        }

        return hash('sha256', $payload);
    }

    /**
     * @return list<string>
     */
    private function files(PackageWorkspace $workspace): array
    {
        $paths = $workspace->build->sourcePaths;

        if ($paths === []) {
            $paths = array_merge(self::DEFAULT_FILES, self::DEFAULT_DIRECTORIES);
        }

        $files = [];

        foreach ($paths as $path) {
            $this->collectPath($workspace, $path, $files);
        }

        $files = array_values(array_unique($files));
        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * @param list<string> $files
     */
    private function collectPath(PackageWorkspace $workspace, string $path, array &$files): void
    {
        $base = rtrim($workspace->path, '/');
        $path = ltrim($path, '/');
        $absolute = $base . '/' . $path;

        if (str_contains($path, '*')) {
            foreach (glob($absolute) ?: [] as $matched) {
                $this->collectAbsolute($workspace, $matched, $files);
            }

            return;
        }

        $this->collectAbsolute($workspace, $absolute, $files);
    }

    /**
     * @param list<string> $files
     */
    private function collectAbsolute(PackageWorkspace $workspace, string $absolute, array &$files): void
    {
        if (is_file($absolute) && is_readable($absolute)) {
            $files[] = $this->normalizePath($absolute);

            return;
        }

        if (!is_dir($absolute)) {
            return;
        }

        try {
            $finder = Finder::create()
                ->files()
                ->ignoreUnreadableDirs()
                ->ignoreVCS(true)
                ->exclude(['node_modules', 'vendor', 'assets', 'var', 'public'])
                ->in($absolute)
                ->sortByName();

            foreach ($finder as $file) {
                $path = $file->getRealPath();

                if (is_string($path)) {
                    $files[] = $this->normalizePath($path);
                }
            }
        } catch (\Throwable $throwable) {
            $this->io->writeError(
                sprintf('Could not inspect asset source path for %s: %s', $workspace->name, $throwable->getMessage()),
                true,
                IOInterface::VERBOSE,
            );
        }
    }

    private function relativePath(PackageWorkspace $workspace, string $file): string
    {
        $base = rtrim($workspace->path, '/') . '/';

        return str_starts_with($file, $base) ? substr($file, strlen($base)) : $file;
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        return rtrim(preg_replace('~/+~', '/', $path) ?: $path, '/');
    }
}
