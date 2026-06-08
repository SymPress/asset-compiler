<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Precompiled;

use Symfony\Component\Filesystem\Filesystem;

final readonly class ArchiveExtractor
{
    public function __construct(private Filesystem $filesystem)
    {
    }

    public function extract(string $archive, string $target, bool $cleanTarget): void
    {
        if ($cleanTarget && is_dir($target)) {
            $this->filesystem->remove($target);
        }

        $this->filesystem->mkdir($target);

        if (preg_match('/\.zip$/i', $archive)) {
            $this->extractZip($archive, $target);

            return;
        }

        throw new \RuntimeException(sprintf('Unsupported precompiled asset archive: %s.', $archive));
    }

    private function extractZip(string $archive, string $target): void
    {
        $zip = new \ZipArchive();

        if ($zip->open($archive) !== true) {
            throw new \RuntimeException(sprintf('Could not open precompiled asset archive: %s.', $archive));
        }

        try {
            for ($index = 0; $index < $zip->numFiles; ++$index) {
                $name = $zip->getNameIndex($index);

                if (!is_string($name) || $name === '' || str_starts_with($name, '/') || str_contains($name, '../')) {
                    continue;
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
                    throw new \RuntimeException(sprintf('Could not write extracted asset file: %s.', $targetPath));
                }

                stream_copy_to_stream($source, $destination);
                fclose($source);
                fclose($destination);
            }
        } finally {
            $zip->close();
        }
    }
}
