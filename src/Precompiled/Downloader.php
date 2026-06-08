<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Precompiled;

use JsonException;
use RuntimeException;

final class Downloader
{
    public function download(string $source, string $target, ?DownloadOptions $options = null): void
    {
        $options ??= DownloadOptions::defaults();

        if (!preg_match('~^https?://~i', $source)) {
            if (!is_file($source)) {
                throw new RuntimeException(sprintf('Precompiled asset archive not found: %s.', $source));
            }

            if (!copy($source, $target)) {
                throw new RuntimeException(sprintf('Could not copy precompiled asset archive: %s.', $source));
            }

            return;
        }

        $this->request($source, $target, $options);
    }

    /**
     * @return array<string, mixed>
     */
    public function json(string $url, ?DownloadOptions $options = null): array
    {
        $target = tempnam(sys_get_temp_dir(), 'sympress_asset_json_');

        if (!is_string($target)) {
            throw new RuntimeException('Could not create a temporary file for the precompiled asset request.');
        }

        try {
            $this->request($url, $target, $options ?? new DownloadOptions(maxBytes: DownloadOptions::JSON_MAX_BYTES));
            $decoded = json_decode((string) file_get_contents($target), true, flags: JSON_THROW_ON_ERROR);

            return is_array($decoded) ? self::stringKeyedArray($decoded) : [];
        } catch (JsonException) {
            return [];
        } finally {
            if (is_file($target)) {
                unlink($target);
            }
        }
    }

    private function request(string $url, string $target, DownloadOptions $options): void
    {
        $currentUrl = $url;

        for ($redirects = 0; $redirects <= $options->maxRedirects; ++$redirects) {
            $result = $this->requestOnce($currentUrl, $target, $options, $redirects > 0);

            if ($result['location'] === null) {
                return;
            }

            $nextUrl = $this->resolveRedirect($currentUrl, $result['location']);

            if (!$options->allowsRedirect($currentUrl, $nextUrl)) {
                throw new RuntimeException(sprintf('Refused unsafe precompiled asset redirect from %s to %s.', $currentUrl, $nextUrl));
            }

            $currentUrl = $nextUrl;
        }

        throw new RuntimeException(sprintf('Too many precompiled asset redirects for %s.', $url));
    }

    /**
     * @return array{location: string|null}
     */
    private function requestOnce(string $url, string $target, DownloadOptions $options, bool $redirected): array
    {
        $handle = curl_init($url);

        if ($handle === false) {
            throw new RuntimeException(sprintf('Could not initialize download for %s.', $url));
        }

        $part = tempnam(dirname($target), 'download_');

        if (!is_string($part)) {
            throw new RuntimeException(sprintf('Could not create temporary precompiled asset target for %s.', $target));
        }

        $file = fopen($part, 'wb');

        if ($file === false) {
            unlink($part);
            throw new RuntimeException(sprintf('Could not write precompiled asset target %s.', $target));
        }

        $location = null;
        $downloadedBytes = 0;
        $tooLarge = false;

        try {
            curl_setopt_array($handle, [
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_FAILONERROR => false,
                CURLOPT_USERAGENT => 'sympress-asset-compiler',
                CURLOPT_HTTPHEADER => $this->headers($options->headersFor($url, $redirected)),
                CURLOPT_CONNECTTIMEOUT => $options->connectTimeout,
                CURLOPT_TIMEOUT => $options->timeout,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HEADERFUNCTION => static function ($handle, string $header) use (&$location): int {
                    $length = strlen($header);

                    if (preg_match('/^Location:\s*(.+)$/i', trim($header), $matches)) {
                        $location = $matches[1];
                    }

                    return $length;
                },
                CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use ($file, $options, &$downloadedBytes, &$tooLarge): int {
                    $length = strlen($chunk);
                    $downloadedBytes += $length;

                    if ($downloadedBytes > $options->maxBytes) {
                        $tooLarge = true;

                        return 0;
                    }

                    $written = fwrite($file, $chunk);

                    return is_int($written) ? $written : 0;
                },
            ]);

            curl_exec($handle);
            $error = curl_error($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        } finally {
            curl_close($handle);
            fclose($file);
        }

        if ($tooLarge) {
            unlink($part);
            throw new RuntimeException(sprintf('Precompiled asset download exceeds the %d byte limit: %s.', $options->maxBytes, $url));
        }

        if ($status >= 300 && $status < 400 && is_string($location) && trim($location) !== '') {
            unlink($part);

            return ['location' => trim($location)];
        }

        if ($error !== '' || $status >= 400) {
            unlink($part);
            throw new RuntimeException(sprintf('Could not download %s. HTTP %d %s', $url, $status, $error));
        }

        if ($status < 200 || $status >= 300) {
            unlink($part);
            throw new RuntimeException(sprintf('Could not download %s. HTTP %d', $url, $status));
        }

        if (!rename($part, $target)) {
            unlink($part);
            throw new RuntimeException(sprintf('Could not save precompiled asset target %s.', $target));
        }

        return ['location' => null];
    }

    /**
     * @param array<string, string> $headers
     * @return list<string>
     */
    private function headers(array $headers): array
    {
        $formatted = [];

        foreach ($headers as $name => $value) {
            $formatted[] = sprintf('%s: %s', $name, $value);
        }

        return $formatted;
    }

    private function resolveRedirect(string $url, string $location): string
    {
        if (preg_match('~^https?://~i', $location)) {
            return $location;
        }

        $parts = parse_url($url);
        $scheme = is_string($parts['scheme'] ?? null) ? $parts['scheme'] : 'https';
        $host = is_string($parts['host'] ?? null) ? $parts['host'] : '';

        if ($host === '') {
            throw new RuntimeException(sprintf('Could not resolve precompiled asset redirect for %s.', $url));
        }

        if (str_starts_with($location, '/')) {
            return sprintf('%s://%s%s', $scheme, $host, $location);
        }

        $path = is_string($parts['path'] ?? null) ? dirname($parts['path']) : '';

        return sprintf('%s://%s/%s/%s', $scheme, $host, trim($path, '/'), ltrim($location, '/'));
    }

    /**
     * @param array<array-key, mixed> $value
     * @return array<string, mixed>
     */
    private static function stringKeyedArray(array $value): array
    {
        $result = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }
}
