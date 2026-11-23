<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Tests\Precompiled;

use PHPUnit\Framework\TestCase;
use SymPress\AssetCompiler\Config\BuildConfig;
use SymPress\AssetCompiler\Config\DependencyMode;
use SymPress\AssetCompiler\Discovery\PackageWorkspace;
use SymPress\AssetCompiler\Precompiled\Downloader;
use SymPress\AssetCompiler\Precompiled\GitHubAssetLocator;

final class GitHubAssetLocatorTest extends TestCase
{
    private const string SHA = '0123456789abcdef0123456789abcdef01234567';

    public function testArtifactSelectionSkipsForkAndOtherCommitArtifacts(): void
    {
        $response = [
            'artifacts' => [
                $this->artifact('fork', self::SHA, headRepositoryId: 999),
                $this->artifact('other-commit', 'ffffffffffffffffffffffffffffffffffffffff'),
                $this->artifact('expired', self::SHA, expired: true),
                ['archive_download_url' => 'https://api.github.com/no-run', 'expired' => false],
                $this->artifact('trusted', strtoupper(self::SHA)),
            ],
        ];

        self::assertSame(
            'https://api.github.com/trusted',
            $this->locator()->artifactDownloadUrl($response, $this->workspace(self::SHA)),
        );
    }

    public function testArtifactSelectionWithoutKnownCommitStillRejectsForks(): void
    {
        $response = [
            'artifacts' => [
                $this->artifact('fork', self::SHA, headRepositoryId: 999),
                $this->artifact('trusted', 'ffffffffffffffffffffffffffffffffffffffff'),
            ],
        ];

        self::assertSame('https://api.github.com/trusted', $this->locator()->artifactDownloadUrl($response, $this->workspace('')));
        self::assertNull($this->locator()->artifactDownloadUrl(['artifacts' => [$response['artifacts'][0]]], $this->workspace('')));
    }

    /** @return array<string, mixed> */
    private function artifact(string $name, string $sha, int $headRepositoryId = 42, bool $expired = false): array
    {
        return [
            'archive_download_url' => 'https://api.github.com/' . $name,
            'expired'              => $expired,
            'workflow_run'         => ['repository_id' => 42, 'head_repository_id' => $headRepositoryId, 'head_sha' => $sha],
        ];
    }

    private function locator(): GitHubAssetLocator
    {
        return new GitHubAssetLocator(new Downloader());
    }

    private function workspace(string $reference): PackageWorkspace
    {
        return new PackageWorkspace(
            name: 'vendor/package',
            type: 'wordpress-plugin',
            path: sys_get_temp_dir(),
            build: new BuildConfig([], DependencyMode::None, null, null, [], [], 120),
            packageJson: [],
            reference: $reference,
        );
    }
}
