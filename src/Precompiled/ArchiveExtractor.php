<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Precompiled;

use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use ZipArchive;

final readonly class ArchiveExtractor
{
    private const int MAX_FILES = 20_000;

    private const int MAX_ENTRY_BYTES = 104_857_600;

    private const int MAX_TOTAL_BYTES = 536_870_912;

    public function __construct(private Filesystem $filesystem)
    {
    }

    public function extract(string $archive, string $target, bool $cleanTarget): void
    {
        $parent = dirname($target);
        $this->filesystem->mkdir($parent);
        $staging = $this->stagingDirectory($parent);

        try {
            if (!preg_match('/\.zip$/i', $archive)) {
                throw new RuntimeException(sprintf('Unsupported precompiled asset archive: %s.', $archive));
            }

            $this->extractZip($archive, $staging);

            if ($cleanTarget) {
                $this->replaceTarget($staging, $target);

                return;
            }

            $this->filesystem->mkdir($target);
            $this->filesystem->mirror($staging, $target, null, ['override' => true]);
        } finally {
            if (is_dir($staging)) {
                $this->filesystem->remove($staging);
            }
        }
    }

    private function extractZip(string $archive, string $target): void
    {
        $zip = new ZipArchive();

        if ($zip->open($archive) !== true) {
            throw new RuntimeException(sprintf('Could not open precompiled asset archive: %s.', $archive));
        }

        try {
            if ($zip->numFiles > self::MAX_FILES) {
                throw new PrecompiledAssetSecurityException(sprintf('Precompiled asset archive contains too many files: %d.', $zip->numFiles));
            }

            $totalBytes = 0;

            for ($index = 0; $index < $zip->numFiles; ++$index) {
                $name = $this->safeEntryName($zip, $index);

                $size = $this->entrySize($zip, $index);
                $totalBytes += $size;

                if ($size > self::MAX_ENTRY_BYTES || $totalBytes > self::MAX_TOTAL_BYTES) {
                    throw new PrecompiledAssetSecurityException('Precompiled asset archive exceeds the configured extraction limits.');
                }

                $targetPath = rtrim($target, '/') . '/' . $name;

                if (str_ends_with($name, '/')) {
                    $this->filesystem->mkdir($targetPath);
                    continue;
                }

                $this->filesystem->mkdir(dirname($targetPath));
                $source = $zip->getStream($name);

                if ($source === false) {
                    continue;
                }

                $destination = fopen($targetPath, 'wb');

                if ($destination === false) {
                    fclose($source);
                    throw new RuntimeException(sprintf('Could not write extracted asset file: %s.', $targetPath));
                }

                try {
                    $this->copyLimited($source, $destination, $size);
                } finally {
                    fclose($source);
                    fclose($destination);
                }
            }
        } finally {
            $zip->close();
        }
    }

    private function safeEntryName(ZipArchive $zip, int $index): string
    {
        $name = $zip->getNameIndex($index);

        if (!is_string($name) || $name === '') {
            throw new PrecompiledAssetSecurityException('Precompiled asset archive contains an unsafe empty entry name.');
        }

        $name = str_replace('\\', '/', $name);
        $segments = explode('/', $name);

        if (str_starts_with($name, '/') || in_array('..', $segments, true)) {
            throw new PrecompiledAssetSecurityException(sprintf('Precompiled asset archive contains an unsafe entry path: %s.', $name));
        }

        while (str_starts_with($name, './')) {
            $name = substr($name, 2);
        }

        if ($name === '') {
            throw new PrecompiledAssetSecurityException('Precompiled asset archive contains an unsafe empty entry name.');
        }

        return $name;
    }

    private function entrySize(ZipArchive $zip, int $index): int
    {
        $stat = $zip->statIndex($index);

        return is_array($stat) ? $stat['size'] : 0;
    }

    /**
     * @param resource $source
     * @param resource $destination
     */
    private function copyLimited($source, $destination, int $expectedBytes): void
    {
        $bytes = 0;

        while (!feof($source)) {
            $chunk = fread($source, 8192);

            if (!is_string($chunk) || $chunk === '') {
                break;
            }

            $bytes += strlen($chunk);

            if ($bytes > self::MAX_ENTRY_BYTES || ($expectedBytes > 0 && $bytes > $expectedBytes)) {
                throw new PrecompiledAssetSecurityException('Precompiled asset archive entry exceeds the configured extraction limits.');
            }

            if (fwrite($destination, $chunk) === false) {
                throw new RuntimeException('Could not write extracted asset file contents.');
            }
        }
    }

    private function stagingDirectory(string $parent): string
    {
        for ($attempt = 0; $attempt < 10; ++$attempt) {
            $path = rtrim($parent, '/') . '/.sympress_asset_extract_' . bin2hex(random_bytes(8));

            if (file_exists($path)) {
                continue;
            }

            $this->filesystem->mkdir($path);

            return $path;
        }

        throw new RuntimeException(sprintf('Could not create staging directory below %s.', $parent));
    }

    private function replaceTarget(string $staging, string $target): void
    {
        $backup = null;

        if (file_exists($target) || is_link($target)) {
            $backup = rtrim(dirname($target), '/') . '/.sympress_asset_backup_' . bin2hex(random_bytes(8));

            if (!rename($target, $backup)) {
                throw new RuntimeException(sprintf('Could not move existing asset target: %s.', $target));
            }
        }

        if (rename($staging, $target)) {
            if ($backup !== null) {
                $this->filesystem->remove($backup);
            }

            return;
        }

        if ($backup !== null && !file_exists($target)) {
            rename($backup, $target);
        }

        throw new RuntimeException(sprintf('Could not replace extracted asset target: %s.', $target));
    }
}
