<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Precompiled;

final class Downloader
{
    /**
     * @param array<string, string> $headers
     */
    public function download(string $source, string $target, array $headers = []): void
    {
        if (!preg_match('~^https?://~i', $source)) {
            if (!is_file($source)) {
                throw new \RuntimeException(sprintf('Precompiled asset archive not found: %s.', $source));
            }

            copy($source, $target);

            return;
        }

        $this->request($source, $target, $headers);
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public function json(string $url, array $headers = []): array
    {
        $target = tempnam(sys_get_temp_dir(), 'sympress_asset_json_');

        if (!is_string($target)) {
            throw new \RuntimeException('Could not create a temporary file for the precompiled asset request.');
        }

        try {
            $this->request($url, $target, $headers);
            $decoded = json_decode((string) file_get_contents($target), true);

            return is_array($decoded) ? $decoded : [];
        } finally {
            if (is_file($target)) {
                unlink($target);
            }
        }
    }

    /**
     * @param array<string, string> $headers
     */
    private function request(string $url, string $target, array $headers): void
    {
        $handle = curl_init($url);

        if ($handle === false) {
            throw new \RuntimeException(sprintf('Could not initialize download for %s.', $url));
        }

        $file = fopen($target, 'wb');

        if ($file === false) {
            curl_close($handle);
            throw new \RuntimeException(sprintf('Could not write precompiled asset target %s.', $target));
        }

        curl_setopt_array($handle, [
            CURLOPT_FILE => $file,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_FAILONERROR => false,
            CURLOPT_USERAGENT => 'sympress-asset-compiler',
            CURLOPT_HTTPHEADER => $this->headers($headers),
        ]);

        curl_exec($handle);
        $error = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        fclose($file);

        if ($error !== '' || $status >= 400) {
            throw new \RuntimeException(sprintf('Could not download %s. HTTP %d %s', $url, $status, $error));
        }
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
}
