<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Precompiled;

final readonly class DownloadOptions
{
    public const int DEFAULT_MAX_BYTES = 104_857_600;

    public const int JSON_MAX_BYTES = 2_097_152;

    private const int DEFAULT_TIMEOUT = 300;

    private const int DEFAULT_CONNECT_TIMEOUT = 15;

    private const int DEFAULT_MAX_REDIRECTS = 5;

    /**
     * @param array<string, string> $headers
     * @param list<string> $headerHostPatterns
     * @param list<string> $redirectHostPatterns
     */
    public function __construct(
        public array $headers = [],
        public array $headerHostPatterns = [],
        public array $redirectHostPatterns = [],
        public int $maxBytes = self::DEFAULT_MAX_BYTES,
        public int $timeout = self::DEFAULT_TIMEOUT,
        public int $connectTimeout = self::DEFAULT_CONNECT_TIMEOUT,
        public int $maxRedirects = self::DEFAULT_MAX_REDIRECTS,
    ) {
    }

    public static function defaults(): self
    {
        return new self();
    }

    /**
     * @param array<string, string> $headers
     */
    public static function githubApi(array $headers): self
    {
        return new self(
            headers: $headers,
            headerHostPatterns: ['api.github.com'],
            redirectHostPatterns: ['api.github.com'],
            maxBytes: self::JSON_MAX_BYTES,
        );
    }

    /**
     * @param array<string, string> $headers
     */
    public static function githubArchive(array $headers): self
    {
        return new self(
            headers: $headers,
            headerHostPatterns: ['api.github.com', 'github.com'],
            redirectHostPatterns: [
                'api.github.com',
                'github.com',
                '*.github.com',
                '*.githubusercontent.com',
                '*.blob.core.windows.net',
            ],
        );
    }

    /**
     * @return array<string, string>
     */
    public function headersFor(string $url, bool $redirected): array
    {
        if ($this->headers === []) {
            return [];
        }

        if ($this->headerHostPatterns === []) {
            return $redirected ? [] : $this->headers;
        }

        return $this->hostMatches($url, $this->headerHostPatterns) ? $this->headers : [];
    }

    public function allowsRedirect(string $fromUrl, string $toUrl): bool
    {
        $fromScheme = $this->scheme($fromUrl);
        $toScheme = $this->scheme($toUrl);

        if (!in_array($toScheme, ['http', 'https'], true)) {
            return false;
        }

        if ($fromScheme === 'https' && $toScheme !== 'https') {
            return false;
        }

        if ($this->redirectHostPatterns === []) {
            return $this->host($fromUrl) === $this->host($toUrl);
        }

        return $this->hostMatches($toUrl, $this->redirectHostPatterns);
    }

    /**
     * @param list<string> $patterns
     */
    private function hostMatches(string $url, array $patterns): bool
    {
        $host = $this->host($url);

        if ($host === '') {
            return false;
        }

        foreach ($patterns as $pattern) {
            $pattern = strtolower($pattern);

            if ($host === $pattern || fnmatch($pattern, $host, FNM_CASEFOLD)) {
                return true;
            }
        }

        return false;
    }

    private function host(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? strtolower($host) : '';
    }

    private function scheme(string $url): string
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        return is_string($scheme) ? strtolower($scheme) : '';
    }
}
