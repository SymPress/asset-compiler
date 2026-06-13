<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Precompiled;

use Composer\IO\IOInterface;
use RuntimeException;
use SymPress\AssetCompiler\Config\PrecompiledAssetConfig;
use SymPress\AssetCompiler\Discovery\PackageWorkspace;
use Throwable;

final readonly class PrecompiledAssetInstaller
{
    public function __construct(
        private Downloader $downloader,
        private ArchiveExtractor $extractor,
        private GitHubAssetLocator $github,
        private IOInterface $io,
    ) {
    }

    public function install(PackageWorkspace $workspace): bool
    {
        foreach ($workspace->build->precompiledAssets as $config) {
            if (!$this->matchesStability($workspace, $config)) {
                continue;
            }

            $archive = null;

            try {
                $source = $this->source($workspace, $config);
                $this->assertSourcePolicy($workspace, $config, $source);
                $archive = $this->download($config, $source);
                $this->verifyChecksum($workspace, $config, $archive);
                $target = $this->target($workspace, $config);
                $cleanTarget = ($config->config['clean-target'] ?? true) !== false;

                $this->extractor->extract($archive, $target, $cleanTarget);

                $this->io->write(
                    sprintf('<info>%s</info> restored precompiled assets via %s.', $workspace->name, $config->adapter),
                );

                return true;
            } catch (Throwable $throwable) {
                if ($throwable instanceof PrecompiledAssetSecurityException) {
                    throw $throwable;
                }

                $this->io->writeError(
                    sprintf(
                        '<warning>%s precompiled assets unavailable:</warning> %s',
                        $workspace->name,
                        $throwable->getMessage(),
                    ),
                    true,
                    IOInterface::VERBOSE,
                );
            } finally {
                if (is_string($archive) && is_file($archive)) {
                    unlink($archive);
                }
            }
        }

        return false;
    }

    private function source(PackageWorkspace $workspace, PrecompiledAssetConfig $config): string
    {
        return match ($config->adapter) {
            'github-release', 'gh-release-zip' => $this->github->releaseAsset($workspace, $config),
            'github-artifact', 'gh-action-artifact' => $this->github->artifact($workspace, $config),
            'archive', 'zip' => $this->github->replace($config->source, $workspace),
            default => throw new RuntimeException(sprintf('Unsupported precompiled asset adapter: %s.', $config->adapter)),
        };
    }

    private function download(PrecompiledAssetConfig $config, string $source): string
    {
        $extension = pathinfo(parse_url($source, PHP_URL_PATH) ?: $source, PATHINFO_EXTENSION) ?: 'zip';
        $archive = tempnam(sys_get_temp_dir(), 'sympress_asset_archive_');

        if (!is_string($archive)) {
            throw new RuntimeException('Could not create a temporary precompiled asset archive.');
        }

        $archiveWithExtension = $archive . '.' . $extension;
        rename($archive, $archiveWithExtension);
        $this->downloader->download($source, $archiveWithExtension, $this->downloadOptions($config));

        return $archiveWithExtension;
    }

    private function downloadOptions(PrecompiledAssetConfig $config): DownloadOptions
    {
        return match ($config->adapter) {
            'github-release', 'gh-release-zip', 'github-artifact', 'gh-action-artifact' => $this->github->archiveOptions($config),
            default => DownloadOptions::defaults(),
        };
    }

    private function target(PackageWorkspace $workspace, PrecompiledAssetConfig $config): string
    {
        $target = $this->safeRelativeTarget($this->github->replace($config->target, $workspace));

        if ($target === null) {
            throw new PrecompiledAssetSecurityException(sprintf('Unsafe precompiled asset target for %s.', $workspace->name));
        }

        $workspacePath = $this->normalizePath($workspace->path);
        $targetPath = $workspacePath . '/' . $target;

        $this->assertPathStaysInsideWorkspace($workspacePath, $targetPath, $workspace->name);

        return $targetPath;
    }

    private function matchesStability(PackageWorkspace $workspace, PrecompiledAssetConfig $config): bool
    {
        if ($config->stability === null || $config->stability === '') {
            return true;
        }

        return $config->stability === $workspace->stability
            || ($config->stability === 'stable' && $workspace->stability !== 'dev');
    }

    private function verifyChecksum(PackageWorkspace $workspace, PrecompiledAssetConfig $config, string $archive): void
    {
        if ($config->checksum === null || trim($config->checksum) === '') {
            return;
        }

        $expected = strtolower($this->github->replace($config->checksum, $workspace));
        $expected = str_starts_with($expected, 'sha256:') ? substr($expected, 7) : $expected;

        if (!preg_match('/^[a-f0-9]{64}$/', $expected)) {
            throw new PrecompiledAssetSecurityException(sprintf('Invalid SHA-256 checksum configured for %s.', $workspace->name));
        }

        $actual = hash_file('sha256', $archive);

        if (!is_string($actual) || !hash_equals($expected, strtolower($actual))) {
            throw new PrecompiledAssetSecurityException(sprintf('Precompiled asset checksum mismatch for %s.', $workspace->name));
        }
    }

    private function assertSourcePolicy(PackageWorkspace $workspace, PrecompiledAssetConfig $config, string $source): void
    {
        if (
            preg_match('~^[a-z][a-z0-9+.-]*://~i', $source)
            && !preg_match('~^https://~i', $source)
        ) {
            throw new PrecompiledAssetSecurityException(sprintf('Insecure non-HTTPS precompiled asset source for %s.', $workspace->name));
        }

        if (
            $workspace->build->requirePrecompiledChecksum
            && preg_match('~^https://~i', $source)
            && ($config->checksum === null || trim($config->checksum) === '')
        ) {
            throw new PrecompiledAssetSecurityException(sprintf('Missing SHA-256 checksum for remote precompiled assets in %s.', $workspace->name));
        }
    }

    private function safeRelativeTarget(string $target): ?string
    {
        $target = trim(str_replace('\\', '/', $target));

        if ($target === '' || str_starts_with($target, '/') || preg_match('/^[A-Za-z]:/', $target) === 1) {
            return null;
        }

        $segments = [];

        foreach (explode('/', $target) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..' || str_contains($segment, "\0")) {
                return null;
            }

            $segments[] = $segment;
        }

        return $segments === [] ? null : implode('/', $segments);
    }

    private function assertPathStaysInsideWorkspace(string $workspacePath, string $targetPath, string $packageName): void
    {
        $realWorkspace = realpath($workspacePath);
        $realTarget = realpath($targetPath);
        $realParent = realpath($this->nearestExistingParent($targetPath));

        if (!is_string($realWorkspace) || !is_string($realParent)) {
            throw new PrecompiledAssetSecurityException(sprintf('Could not validate precompiled asset target for %s.', $packageName));
        }

        $realWorkspace = $this->normalizePath($realWorkspace);
        $realParent = $this->normalizePath($realParent);

        if (!$this->isSameOrChildPath($realParent, $realWorkspace)) {
            throw new PrecompiledAssetSecurityException(sprintf('Unsafe precompiled asset target for %s.', $packageName));
        }

        if (!is_string($realTarget)) {
            return;
        }

        $realTarget = $this->normalizePath($realTarget);

        if (!$this->isSameOrChildPath($realTarget, $realWorkspace)) {
            throw new PrecompiledAssetSecurityException(sprintf('Unsafe precompiled asset target for %s.', $packageName));
        }
    }

    private function nearestExistingParent(string $path): string
    {
        $parent = dirname($path);

        while (!is_dir($parent) && dirname($parent) !== $parent) {
            $parent = dirname($parent);
        }

        return $parent;
    }

    private function isSameOrChildPath(string $path, string $parent): bool
    {
        return $path === $parent || str_starts_with($path, rtrim($parent, '/') . '/');
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        return rtrim(preg_replace('~/+~', '/', $path) ?: $path, '/');
    }
}
