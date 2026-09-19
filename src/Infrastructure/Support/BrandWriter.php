<?php

declare(strict_types=1);

namespace Aster\Infrastructure\Support;

use RuntimeException;

/**
 * Atomically saves storage/CompanyBrand.json.
 *
 * Written to a temp file in the same directory and then renamed into place:
 * rename() is atomic on both POSIX and Windows filesystems, so a request
 * that reads the file mid-save (BrandResolver, or a browser loading the
 * public site) always sees either the old, fully-written file or the new
 * one - never a half-written one.
 */
final class BrandWriter
{
    public function __construct(private readonly BrandResolver $resolver)
    {
    }

    /**
     * @param array<string, mixed> $payload the {"identity": ..., "theme": ...} structure
     * @throws RuntimeException when public/ is not writable by the web server user
     */
    public function save(array $payload): void
    {
        $finalPath = $this->resolver->path();
        $directory = dirname($finalPath);

        if (!is_writable($directory)) {
            throw new RuntimeException(
                "storage/ is not writable by the web server user - ask your host to chmod it.",
            );
        }

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new RuntimeException('Could not encode the brand settings as JSON.');
        }

        $tmpPath = $finalPath . '.' . bin2hex(random_bytes(6)) . '.tmp';

        if (file_put_contents($tmpPath, $json, LOCK_EX) === false) {
            throw new RuntimeException('Could not write the brand settings file.');
        }

        if (!rename($tmpPath, $finalPath)) {
            @unlink($tmpPath);

            throw new RuntimeException('Could not save the brand settings file.');
        }

        @chmod($finalPath, 0644);
    }
}
