<?php

declare(strict_types=1);

namespace MediCareMini\Infrastructure\Storage;

/**
 * Metadata for a file that has been accepted and written to disk.
 *
 * `relativePath` is always relative to its storage root (proofs or media),
 * never an absolute path and never a URL, so the storage location can move
 * without rewriting any database row.
 */
final readonly class StoredFile
{
    public function __construct(
        public string $relativePath,
        /** The patient's original filename, kept for display only. */
        public string $originalName,
        public string $mimeType,
        public int $size,
        public string $sha256,
    ) {
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mimeType, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mimeType === 'application/pdf';
    }

    public function sizeLabel(): string
    {
        return $this->size >= 1048576
            ? round($this->size / 1048576, 1) . ' MB'
            : max(1, (int) round($this->size / 1024)) . ' KB';
    }

    /** Public URL for a media file. Proofs never have one by design. */
    public function mediaUrl(): string
    {
        return '/media/' . $this->relativePath;
    }
}
