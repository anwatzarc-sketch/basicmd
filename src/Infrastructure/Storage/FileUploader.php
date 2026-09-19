<?php

declare(strict_types=1);

namespace MediCareMini\Infrastructure\Storage;

use MediCareMini\Domain\Exception\ValidationException;
use MediCareMini\Infrastructure\Support\Config;
use MediCareMini\Infrastructure\Support\Logger;
use RuntimeException;

/**
 * Validated file uploads.
 *
 * Upload handling is where most PHP applications get compromised, so the
 * rules here are deliberately strict and layered:
 *
 *  1. The client-supplied MIME type and filename are treated as decoration.
 *     Type is determined by finfo reading the file's own magic bytes.
 *  2. Images are re-encoded through GD rather than moved. A polyglot file
 *     that is both a valid JPEG and valid PHP does not survive being decoded
 *     to a pixel buffer and written back out.
 *  3. Stored names are random. The original is kept only as a display label,
 *     so a crafted filename can never influence a path.
 *  4. Payment proofs are written OUTSIDE the webroot and streamed through an
 *     authenticated controller; even a mis-configured web server cannot serve
 *     one directly.
 */
final readonly class FileUploader
{
    /** Extension per accepted image type, keyed by the finfo result. */
    private const array IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    private const string PDF_TYPE = 'application/pdf';

    public function __construct(
        private Config $config,
        private Logger $logger,
    ) {
    }

    /**
     * Store a proof-of-payment file.
     *
     * Accepts images and PDFs: patients photograph a bank slip, screenshot a
     * Telebirr confirmation, or download a PDF statement, and all three are
     * legitimate evidence.
     *
     * @param array<string, mixed> $file a $_FILES entry
     * @throws ValidationException
     */
    public function storeProof(array $file, string $bookingRef): StoredFile
    {
        $this->assertUploadOk($file, $this->config->proofMaxBytes());

        $tmpPath  = (string) $file['tmp_name'];
        $mimeType = $this->detectMimeType($tmpPath);

        $isImage = isset(self::IMAGE_TYPES[$mimeType]);
        $isPdf   = $mimeType === self::PDF_TYPE;

        if (!$isImage && !$isPdf) {
            throw ValidationException::single(
                'proof',
                'Please upload a JPG, PNG, WebP or PDF file.',
            );
        }

        // Group by booking reference so a support request ("find the slips
        // for AMC-7F3K9Q2B") is one directory listing rather than a query.
        $directory = $this->config->proofDir() . DIRECTORY_SEPARATOR . $this->safeSegment($bookingRef);
        $this->ensureDirectory($directory);

        $extension = $isPdf ? 'pdf' : self::IMAGE_TYPES[$mimeType];
        $filename  = date('Ymd-His') . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
        $target    = $directory . DIRECTORY_SEPARATOR . $filename;

        if ($isImage) {
            // Re-encode, which both strips any embedded payload and removes
            // EXIF - including the GPS coordinates phones attach by default.
            $this->reencodeImage($tmpPath, $target, $mimeType, maxDimension: 2400);
        } else {
            $this->assertPdfIsPlausible($tmpPath);

            if (!move_uploaded_file($tmpPath, $target)) {
                throw new RuntimeException('Failed to store the uploaded file.');
            }
        }

        @chmod($target, 0640);

        $size = (int) (filesize($target) ?: 0);
        $hash = hash_file('sha256', $target) ?: '';

        // Path stored relative to the proofs root; the absolute path is
        // rebuilt at read time so moving storage does not invalidate rows.
        $relativePath = $this->safeSegment($bookingRef) . '/' . $filename;

        $this->logger->info('Payment proof stored', [
            'booking_ref' => $bookingRef,
            'size'        => $size,
            'mime'        => $mimeType,
        ]);

        return new StoredFile(
            relativePath: $relativePath,
            originalName: $this->sanitiseDisplayName((string) ($file['name'] ?? 'receipt')),
            mimeType:     $isPdf ? self::PDF_TYPE : $mimeType,
            size:         $size,
            sha256:       $hash,
        );
    }

    /**
     * Store a CMS image (doctor photo, article cover, facility picture).
     *
     * Always converted to WebP: typically 25-35% smaller than equivalent JPEG,
     * which matters a great deal for patients on mobile data in Addis Ababa.
     *
     * @param array<string, mixed> $file
     * @throws ValidationException
     */
    public function storeMedia(array $file, string $folder, int $maxDimension = 1600): StoredFile
    {
        $this->assertUploadOk($file, $this->config->mediaMaxBytes());

        $tmpPath  = (string) $file['tmp_name'];
        $mimeType = $this->detectMimeType($tmpPath);

        if (!isset(self::IMAGE_TYPES[$mimeType])) {
            throw ValidationException::single('image', 'Please upload a JPG, PNG or WebP image.');
        }

        $directory = $this->config->mediaDir() . DIRECTORY_SEPARATOR . $this->safeSegment($folder);
        $this->ensureDirectory($directory);

        $filename = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.webp';
        $target   = $directory . DIRECTORY_SEPARATOR . $filename;

        $this->reencodeImage($tmpPath, $target, $mimeType, $maxDimension, forceWebp: true);

        @chmod($target, 0644);

        return new StoredFile(
            relativePath: $this->safeSegment($folder) . '/' . $filename,
            originalName: $this->sanitiseDisplayName((string) ($file['name'] ?? 'image')),
            mimeType:     'image/webp',
            size:         (int) (filesize($target) ?: 0),
            sha256:       hash_file('sha256', $target) ?: '',
        );
    }

    /**
     * Check PHP's own upload result and the size ceiling.
     *
     * @param array<string, mixed> $file
     * @throws ValidationException
     */
    private function assertUploadOk(array $file, int $maxBytes): void
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            throw ValidationException::single('proof', match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => sprintf(
                    'That file is too large. The maximum size is %d MB.',
                    intdiv($maxBytes, 1048576),
                ),
                UPLOAD_ERR_PARTIAL  => 'The upload was interrupted. Please try again.',
                UPLOAD_ERR_NO_FILE  => 'Please attach your receipt.',
                UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'We could not save your file. Please try again shortly.',
                default             => 'The upload failed. Please try again.',
            });
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');

        // The definitive check that this really came from an HTTP upload and
        // is not an arbitrary server path smuggled into the request.
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw ValidationException::single('proof', 'The upload could not be verified. Please try again.');
        }

        $size = (int) ($file['size'] ?? 0);

        if ($size <= 0) {
            throw ValidationException::single('proof', 'The file appears to be empty.');
        }

        if ($size > $maxBytes) {
            throw ValidationException::single('proof', sprintf(
                'That file is too large. The maximum size is %d MB.',
                intdiv($maxBytes, 1048576),
            ));
        }
    }

    /** Read the real type from the file's magic bytes. */
    private function detectMimeType(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            throw new RuntimeException('fileinfo is unavailable; cannot verify upload type.');
        }

        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);

        return is_string($mime) ? strtolower($mime) : 'application/octet-stream';
    }

    /**
     * Decode and re-encode an image, downscaling if oversized.
     *
     * The decode/encode round trip is the sanitisation step: whatever else
     * was in the container, only pixels survive.
     */
    private function reencodeImage(
        string $sourcePath,
        string $targetPath,
        string $mimeType,
        int $maxDimension,
        bool $forceWebp = false,
    ): void {
        $image = match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png'  => @imagecreatefrompng($sourcePath),
            'image/webp' => @imagecreatefromwebp($sourcePath),
            default      => false,
        };

        if ($image === false) {
            throw ValidationException::single('proof', 'That image could not be read. Please try a different file.');
        }

        try {
            $width  = imagesx($image);
            $height = imagesy($image);

            if ($width > $maxDimension || $height > $maxDimension) {
                $scale     = $maxDimension / max($width, $height);
                $newWidth  = max(1, (int) round($width * $scale));
                $newHeight = max(1, (int) round($height * $scale));

                $resized = imagecreatetruecolor($newWidth, $newHeight);

                // Preserve transparency for PNG and WebP sources, or logos
                // come out with a black background.
                imagealphablending($resized, false);
                imagesavealpha($resized, true);

                imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                imagedestroy($image);
                $image = $resized;
            }

            $quality = $this->config->webpQuality();

            $written = match (true) {
                $forceWebp || $mimeType === 'image/webp' => imagewebp($image, $targetPath, $quality),
                $mimeType === 'image/png'                => imagepng($image, $targetPath, 6),
                default                                  => imagejpeg($image, $targetPath, $quality),
            };

            if ($written === false) {
                throw new RuntimeException('Failed to write the processed image.');
            }
        } finally {
            if ($image !== false) {
                imagedestroy($image);
            }
        }
    }

    /**
     * Sanity-check a PDF.
     *
     * PDFs cannot be re-encoded the way images can, so the defences are the
     * header check plus never serving the file inline from the app's own
     * origin without nosniff and an authenticated route.
     *
     * @throws ValidationException
     */
    private function assertPdfIsPlausible(string $path): void
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw ValidationException::single('proof', 'The file could not be read.');
        }

        $header = (string) fread($handle, 5);
        fclose($handle);

        if (!str_starts_with($header, '%PDF-')) {
            throw ValidationException::single('proof', 'That file is not a valid PDF.');
        }
    }

    /** Strip anything that could escape the storage directory. */
    private function safeSegment(string $segment): string
    {
        $clean = preg_replace('/[^A-Za-z0-9_\-]/', '', $segment) ?? '';

        return $clean === '' ? 'misc' : mb_substr($clean, 0, 64);
    }

    /** Keep the original name readable but inert; never used as a path. */
    private function sanitiseDisplayName(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/[\x00-\x1F\x7F<>:"\/\\\\|?*]/', '', $name) ?? $name;

        return mb_substr(trim($name), 0, 255) ?: 'upload';
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!@mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException("Could not create upload directory: {$directory}");
        }
    }

    /** Absolute path of a stored proof, for the streaming controller. */
    public function proofPath(string $relativePath): string
    {
        return $this->config->proofDir() . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }

    /**
     * Resolve and verify a proof path stays inside the proofs directory.
     *
     * Defence in depth: the stored value is generated by this class and
     * cannot contain traversal, but the check costs nothing and guards
     * against a tampered database row.
     */
    public function resolveProofPath(string $relativePath): ?string
    {
        $absolute = realpath($this->proofPath($relativePath));
        $root     = realpath($this->config->proofDir());

        if ($absolute === false || $root === false || !str_starts_with($absolute, $root)) {
            return null;
        }

        return is_file($absolute) ? $absolute : null;
    }

    public function deleteMedia(string $relativePath): bool
    {
        $absolute = realpath($this->config->mediaDir() . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
        $root     = realpath($this->config->mediaDir());

        if ($absolute === false || $root === false || !str_starts_with($absolute, $root)) {
            return false;
        }

        return @unlink($absolute);
    }
}
