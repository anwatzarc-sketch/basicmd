<?php

declare(strict_types=1);

namespace Aster\Domain\Exception;

use RuntimeException;

/**
 * Thrown when encrypted clinical data cannot be produced or read back.
 *
 * FRS 11.2: "Never silently return corrupted or undecryptable clinical
 * data as valid data." A caught, logged exception is the honest failure
 * mode here - showing an empty allergy list because decryption silently
 * failed would be a patient-safety defect, not a cosmetic one.
 */
final class EncryptionException extends RuntimeException
{
    public static function missingKey(): self
    {
        return new self(
            'APP_KEY is not configured. Generate one with '
            . '"php -r \'echo base64_encode(random_bytes(32));\'" and set it in .env.',
        );
    }

    public static function invalidKey(): self
    {
        return new self('APP_KEY is not a valid base64-encoded 32-byte key.');
    }

    public static function encryptionFailed(): self
    {
        return new self('Encryption failed.');
    }

    public static function tamperedOrCorrupted(): self
    {
        return new self('Encrypted value could not be authenticated - it is corrupted, tampered with, or was encrypted with a different key.');
    }

    public static function malformedCiphertext(): self
    {
        return new self('Stored value is not in the expected encrypted format.');
    }
}
