<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Tests\Precompiled;

use PHPUnit\Framework\TestCase;
use SymPress\AssetCompiler\Precompiled\DownloadOptions;

final class DownloadOptionsTest extends TestCase
{
    public function testHeadersAreNotSentToUntrustedRedirectHosts(): void
    {
        $options = DownloadOptions::githubArchive(['Authorization' => 'Bearer token']);

        self::assertSame(
            ['Authorization' => 'Bearer token'],
            $options->headersFor('https://api.github.com/repos/acme/package/releases/assets/1', false),
        );
        self::assertSame([], $options->headersFor('https://objects.githubusercontent.com/archive.zip', true));
    }

    public function testRejectsUnsafeRedirectTargets(): void
    {
        $options = DownloadOptions::githubArchive([]);

        self::assertTrue($options->allowsRedirect(
            'https://api.github.com/repos/acme/package/actions/artifacts/1/zip',
            'https://objects.githubusercontent.com/archive.zip',
        ));
        self::assertFalse($options->allowsRedirect(
            'https://api.github.com/repos/acme/package/actions/artifacts/1/zip',
            'http://objects.githubusercontent.com/archive.zip',
        ));
        self::assertFalse($options->allowsRedirect(
            'https://api.github.com/repos/acme/package/actions/artifacts/1/zip',
            'https://example.test/archive.zip',
        ));
    }
}
