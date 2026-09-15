<?php

declare(strict_types=1);

namespace Aster\Tests\Unit\Infrastructure\Security;

use Aster\Domain\Exception\EncryptionException;
use Aster\Infrastructure\Security\Encryptor;
use PHPUnit\Framework\TestCase;

final class EncryptorTest extends TestCase
{
    private function makeKey(): string
    {
        return base64_encode(random_bytes(32));
    }

    // -----------------------------------------------------------------
    //  Key validation
    // -----------------------------------------------------------------

    public function test_rejects_an_empty_key(): void
    {
        $this->expectException(EncryptionException::class);
        new Encryptor('');
    }

    public function test_rejects_a_key_that_is_not_valid_base64(): void
    {
        $this->expectException(EncryptionException::class);
        new Encryptor('not valid base64!!! ###');
    }

    public function test_rejects_a_key_of_the_wrong_byte_length(): void
    {
        $this->expectException(EncryptionException::class);
        new Encryptor(base64_encode(random_bytes(16))); // AES-128 length, not 256
    }

    public function test_accepts_a_well_formed_32_byte_key(): void
    {
        $encryptor = new Encryptor($this->makeKey());
        self::assertInstanceOf(Encryptor::class, $encryptor);
    }

    // -----------------------------------------------------------------
    //  Round trip
    // -----------------------------------------------------------------

    public function test_encrypt_then_decrypt_recovers_the_original_plaintext(): void
    {
        $encryptor = new Encryptor($this->makeKey());
        $plaintext = 'Penicillin allergy - anaphylaxis in 2019.';

        $encrypted = $encryptor->encrypt($plaintext);
        $decrypted = $encryptor->decrypt($encrypted);

        self::assertSame($plaintext, $decrypted);
    }

    public function test_round_trips_unicode_and_amharic_text(): void
    {
        $encryptor = new Encryptor($this->makeKey());
        $plaintext = 'የፔኒሲሊን አለርጂ - Penicillin allergy, emoji: 🩺💊';

        self::assertSame($plaintext, $encryptor->decrypt($encryptor->encrypt($plaintext)));
    }

    public function test_round_trips_an_empty_string(): void
    {
        $encryptor = new Encryptor($this->makeKey());

        self::assertSame('', $encryptor->decrypt($encryptor->encrypt('')));
    }

    public function test_round_trips_a_json_payload(): void
    {
        $encryptor = new Encryptor($this->makeKey());
        $json      = json_encode(['allergies' => ['penicillin', 'peanuts'], 'severity' => 'high']);

        self::assertSame($json, $encryptor->decrypt($encryptor->encrypt($json)));
    }

    // -----------------------------------------------------------------
    //  Ciphertext shape
    // -----------------------------------------------------------------

    public function test_encrypted_output_never_contains_the_plaintext(): void
    {
        $encryptor = new Encryptor($this->makeKey());
        $plaintext = 'a very distinctive marker string 8f3k2';

        self::assertStringNotContainsString($plaintext, $encryptor->encrypt($plaintext));
    }

    public function test_two_encryptions_of_the_same_plaintext_produce_different_ciphertext(): void
    {
        // The nonce is random per call - identical plaintext must not
        // produce identical ciphertext, or an attacker could spot repeats.
        $encryptor = new Encryptor($this->makeKey());
        $plaintext = 'same allergy note';

        self::assertNotSame($encryptor->encrypt($plaintext), $encryptor->encrypt($plaintext));
    }

    public function test_looks_encrypted_recognises_real_output_and_rejects_plain_text(): void
    {
        $encryptor = new Encryptor($this->makeKey());

        self::assertTrue($encryptor->looksEncrypted($encryptor->encrypt('x')));
        self::assertFalse($encryptor->looksEncrypted('plain text, never encrypted'));
        self::assertFalse($encryptor->looksEncrypted(null));
    }

    // -----------------------------------------------------------------
    //  The property FRS 11.2 exists for: never return corrupted data as
    //  valid data.
    // -----------------------------------------------------------------

    public function test_decrypting_with_the_wrong_key_throws_rather_than_returning_garbage(): void
    {
        $encrypted = (new Encryptor($this->makeKey()))->encrypt('secret allergy note');

        $this->expectException(EncryptionException::class);
        (new Encryptor($this->makeKey()))->decrypt($encrypted);
    }

    public function test_a_single_flipped_byte_in_the_ciphertext_is_detected(): void
    {
        $encryptor = new Encryptor($this->makeKey());
        $encrypted = $encryptor->encrypt('critical allergy: latex');

        $tampered = $this->flipOneByte($encrypted);

        $this->expectException(EncryptionException::class);
        $encryptor->decrypt($tampered);
    }

    public function test_a_truncated_ciphertext_is_rejected(): void
    {
        $encryptor = new Encryptor($this->makeKey());
        $encrypted = $encryptor->encrypt('some clinical text');

        $this->expectException(EncryptionException::class);
        $encryptor->decrypt(substr($encrypted, 0, -10));
    }

    public function test_a_value_with_no_recognised_prefix_is_rejected(): void
    {
        $encryptor = new Encryptor($this->makeKey());

        $this->expectException(EncryptionException::class);
        $encryptor->decrypt('plain text that was never encrypted at all');
    }

    public function test_an_empty_string_is_rejected_as_not_encrypted(): void
    {
        $encryptor = new Encryptor($this->makeKey());

        $this->expectException(EncryptionException::class);
        $encryptor->decrypt('');
    }

    public function test_valid_base64_that_is_too_short_to_contain_nonce_and_tag_is_rejected(): void
    {
        $encryptor = new Encryptor($this->makeKey());

        $this->expectException(EncryptionException::class);
        $encryptor->decrypt('aes256gcm.v1.' . base64_encode('short'));
    }

    /** Find a byte in the base64 payload that, when flipped, still decodes as valid base64. */
    private function flipOneByte(string $encrypted): string
    {
        $prefix  = 'aes256gcm.v1.';
        $payload = substr($encrypted, strlen($prefix));
        $bytes   = str_split($payload);

        // Flip a character in the middle of the base64 alphabet-safe zone.
        $index          = (int) floor(count($bytes) / 2);
        $bytes[$index]  = $bytes[$index] === 'A' ? 'B' : 'A';

        return $prefix . implode('', $bytes);
    }
}
