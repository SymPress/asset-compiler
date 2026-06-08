<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Precompiled;

use RuntimeException;
use SymPress\AssetCompiler\Config\PrecompiledAssetConfig;
use SymPress\AssetCompiler\Discovery\PackageWorkspace;

final readonly class GitHubAssetLocator
{
    public function __construct(private Downloader $downloader)
    {
    }

    public function releaseAsset(PackageWorkspace $workspace, PrecompiledAssetConfig $config): string
    {
        $repository = $this->repository($config);
        $configuredTag = $config->config['tag'] ?? $workspace->version;
        $tag = $this->replace(is_string($configuredTag) ? $configuredTag : $workspace->version, $workspace);
        $asset = $this->replace($config->source, $workspace);
        $release = $this->downloader->json(
            sprintf('https://api.github.com/repos/%s/releases/tags/%s', $repository, rawurlencode($tag)),
            DownloadOptions::githubApi($this->apiHeaders($config)),
        );
        $assets = $release['assets'] ?? [];

        if (!is_iterable($assets)) {
            $assets = [];
        }

        foreach ($assets as $candidate) {
            if (is_array($candidate) && ($candidate['name'] ?? null) === $asset && is_string($candidate['url'] ?? null)) {
                return $candidate['url'];
            }
        }

        throw new RuntimeException(sprintf('GitHub release asset %s was not found in %s@%s.', $asset, $repository, $tag));
    }

    public function artifact(PackageWorkspace $workspace, PrecompiledAssetConfig $config): string
    {
        $repository = $this->repository($config);
        $name = $this->replace($config->source, $workspace);
        $artifacts = $this->downloader->json(
            sprintf('https://api.github.com/repos/%s/actions/artifacts?per_page=100&name=%s', $repository, rawurlencode($name)),
            DownloadOptions::githubApi($this->apiHeaders($config)),
        );
        $artifactList = $artifacts['artifacts'] ?? [];

        if (!is_iterable($artifactList)) {
            $artifactList = [];
        }

        foreach ($artifactList as $artifact) {
            if (
                is_array($artifact)
                && ($artifact['expired'] ?? true) === false
                && is_string($artifact['archive_download_url'] ?? null)
            ) {
                return $artifact['archive_download_url'];
            }
        }

        throw new RuntimeException(sprintf('GitHub artifact %s was not found in %s.', $name, $repository));
    }

    public function archiveOptions(PrecompiledAssetConfig $config): DownloadOptions
    {
        return DownloadOptions::githubArchive($this->archiveHeaders($config));
    }

    public function replace(string $value, PackageWorkspace $workspace): string
    {
        [$vendor, $package] = array_pad(explode('/', $workspace->name, 2), 2, '');

        return strtr($value, [
            '${name}' => $workspace->name,
            '${vendor}' => $vendor,
            '${package}' => $package,
            '${version}' => $workspace->version,
            '${ref}' => $workspace->reference,
            '${reference}' => $workspace->reference,
            '${stability}' => $workspace->stability,
        ]);
    }

    private function repository(PrecompiledAssetConfig $config): string
    {
        $repository = $config->config['repository'] ?? null;

        if (!is_string($repository) || trim($repository) === '') {
            throw new RuntimeException('GitHub precompiled assets require config.repository.');
        }

        return trim($repository);
    }

    /**
     * @return array<string, string>
     */
    private function apiHeaders(PrecompiledAssetConfig $config): array
    {
        $token = $config->config['token'] ?? getenv('GITHUB_TOKEN') ?: getenv('GH_TOKEN');
        $headers = ['Accept' => 'application/vnd.github+json'];

        if (is_string($token) && trim($token) !== '') {
            $headers['Authorization'] = 'Bearer ' . trim($token);
        }

        return $headers;
    }

    /**
     * @return array<string, string>
     */
    private function archiveHeaders(PrecompiledAssetConfig $config): array
    {
        $token = $config->config['token'] ?? getenv('GITHUB_TOKEN') ?: getenv('GH_TOKEN');
        $headers = ['Accept' => 'application/octet-stream'];

        if (is_string($token) && trim($token) !== '') {
            $headers['Authorization'] = 'Bearer ' . trim($token);
        }

        return $headers;
    }
}
