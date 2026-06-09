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

            try {
                $source = $this->source($workspace, $config);
                $archive = $this->download($config, $source);
                $this->verifyChecksum($workspace, $config, $archive);
                $target = $this->target($workspace, $config);
                $cleanTarget = ($config->config['clean-target'] ?? true) !== false;

                try {
                    $this->extractor->extract($archive, $target, $cleanTarget);
                } finally {
                    if (is_file($archive)) {
                        unlink($archive);
                    }
                }

                $this->io->write(
                    sprintf('<info>%s</info> restored precompiled assets via %s.', $workspace->name, $config->adapter),
                );

                return true;
            } catch (Throwable $throwable) {
                $this->io->writeError(
                    sprintf(
                        '<warning>%s precompiled assets unavailable:</warning> %s',
                        $workspace->name,
                        $throwable->getMessage(),
                    ),
                    true,
                    IOInterface::VERBOSE,
                );
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
        $target = ltrim($this->github->replace($config->target, $workspace), '/');

        if ($target === '' || str_contains($target, '../')) {
            throw new RuntimeException(sprintf('Unsafe precompiled asset target for %s.', $workspace->name));
        }

        return rtrim($workspace->path, '/') . '/' . $target;
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
            throw new RuntimeException(sprintf('Invalid SHA-256 checksum configured for %s.', $workspace->name));
        }

        $actual = hash_file('sha256', $archive);

        if (!is_string($actual) || !hash_equals($expected, strtolower($actual))) {
            throw new RuntimeException(sprintf('Precompiled asset checksum mismatch for %s.', $workspace->name));
        }
    }
}
