<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Precompiled;

use Composer\IO\IOInterface;
use SymPress\AssetCompiler\Config\PrecompiledAssetConfig;
use SymPress\AssetCompiler\Discovery\PackageWorkspace;

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
                $archive = $this->download($workspace, $config, $source);
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
            } catch (\Throwable $throwable) {
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
            default => throw new \RuntimeException(sprintf('Unsupported precompiled asset adapter: %s.', $config->adapter)),
        };
    }

    private function download(PackageWorkspace $workspace, PrecompiledAssetConfig $config, string $source): string
    {
        $extension = pathinfo(parse_url($source, PHP_URL_PATH) ?: $source, PATHINFO_EXTENSION) ?: 'zip';
        $archive = tempnam(sys_get_temp_dir(), 'sympress_asset_archive_');

        if (!is_string($archive)) {
            throw new \RuntimeException('Could not create a temporary precompiled asset archive.');
        }

        $archiveWithExtension = $archive . '.' . $extension;
        rename($archive, $archiveWithExtension);
        $headers = $this->downloadHeaders($config);
        $this->downloader->download($source, $archiveWithExtension, $headers);

        return $archiveWithExtension;
    }

    private function target(PackageWorkspace $workspace, PrecompiledAssetConfig $config): string
    {
        $target = ltrim($this->github->replace($config->target, $workspace), '/');

        if ($target === '' || str_contains($target, '../')) {
            throw new \RuntimeException(sprintf('Unsafe precompiled asset target for %s.', $workspace->name));
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

    /**
     * @return array<string, string>
     */
    private function downloadHeaders(PrecompiledAssetConfig $config): array
    {
        $token = $config->config['token'] ?? getenv('GITHUB_TOKEN') ?: getenv('GH_TOKEN');

        return is_string($token) && trim($token) !== ''
            ? ['Authorization' => 'Bearer ' . trim($token)]
            : [];
    }
}
