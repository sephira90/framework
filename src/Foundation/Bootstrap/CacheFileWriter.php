<?php

declare(strict_types=1);

namespace Framework\Foundation\Bootstrap;

use RuntimeException;

/**
 * Persists generated cache files with a single replacement step.
 */
final class CacheFileWriter
{
    public function write(string $path, string $contents): void
    {
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create cache directory [%s].', $directory));
        }

        $temporaryPath = tempnam($directory, 'framework-cache-');

        if ($temporaryPath === false) {
            throw new RuntimeException(sprintf('Unable to create a temporary cache file in [%s].', $directory));
        }

        try {
            if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false) {
                throw new RuntimeException(sprintf('Unable to write cache file [%s].', $path));
            }

            if (!rename($temporaryPath, $path)) {
                throw new RuntimeException(sprintf('Unable to move cache file into [%s].', $path));
            }
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }
}
