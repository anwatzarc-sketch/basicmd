<?php

declare(strict_types=1);

namespace MediCareMini\Infrastructure\Security;

use MediCareMini\Domain\Exception\ValidationException;
use SensitiveParameter;

/**
 * Argon2id password hashing with transparent rehash-on-login.
 *
 * Argon2id rather than bcrypt: it is memory-hard, so an attacker with GPUs
 * gains far less. Cost parameters come from config, and needsRehash() means
 * raising them later silently upgrades each user's hash the next time they
 * sign in - no forced reset, no migration script.
 */
final readonly class PasswordHasher
{
    private const int MIN_LENGTH = 12;

    /** @param array{memory_cost:int, time_cost:int, threads:int} $options */
    public function __construct(private array $options)
    {
    }

    public function hash(#[SensitiveParameter] string $plain): string
    {
        return password_hash($plain, PASSWORD_ARGON2ID, $this->options);
    }

    /**
     * Verify a candidate password.
     *
     * When the stored value is not a usable hash, a dummy verify still runs
     * so the response time does not reveal whether the account exists.
     */
    public function verify(#[SensitiveParameter] string $plain, string $hash): bool
    {
        if ($hash === '' || $hash === '!') {
            $this->burnTime();

            return false;
        }

        return password_verify($plain, $hash);
    }

    /** True when $hash was made with weaker parameters than we now require. */
    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, $this->options);
    }

    /**
     * Spend comparable CPU time on a non-existent account.
     *
     * Without this, "user not found" returns in microseconds while a real
     * account takes ~100 ms, which is enough to enumerate valid staff emails.
     */
    public function burnTime(): void
    {
        password_verify(
            'timing-equalisation',
            '$argon2id$v=19$m=65536,t=4,p=2$WUdVeDBkM2NkTVJvMFJqUQ$0Tr9kx1lVIk0PbLhXrJbGNNfYQvlJqLLhVnMdOX5Vqw',
        );
    }

    /**
     * Enforce the password policy.
     *
     * Length is weighted far more heavily than character-class rules, which
     * mostly produce "Password1!" and a sticky note. The blocklist catches
     * the handful of guesses that any credential-stuffing run tries first.
     *
     * @throws ValidationException
     */
    public static function assertStrong(
        #[SensitiveParameter] string $plain,
        string $field = 'password',
    ): void {
        $errors = [];

        if (mb_strlen($plain) < self::MIN_LENGTH) {
            $errors[] = sprintf('Use at least %d characters.', self::MIN_LENGTH);
        }

        if (mb_strlen($plain) > 200) {
            $errors[] = 'Passwords cannot exceed 200 characters.';
        }

        if (preg_match('/[a-z]/', $plain) !== 1 || preg_match('/[A-Z]/', $plain) !== 1) {
            $errors[] = 'Include both uppercase and lowercase letters.';
        }

        if (preg_match('/\d/', $plain) !== 1) {
            $errors[] = 'Include at least one number.';
        }

        if (preg_match('/[^a-zA-Z0-9]/', $plain) !== 1) {
            $errors[] = 'Include at least one symbol.';
        }

        $lowered = strtolower($plain);
        $common  = [
            'password', 'passw0rd', 'qwerty', '123456', '12345678', 'letmein',
            'welcome', 'admin', 'administrator', 'medicaremini', 'radiants',
            'medical', 'clinic', 'hospital', 'addisababa', 'ethiopia',
        ];

        foreach ($common as $banned) {
            if (str_contains($lowered, $banned)) {
                $errors[] = 'Avoid common words, the clinic name or place names.';
                break;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withErrors(
                [$field => $errors],
                'That password is not strong enough.',
            );
        }
    }

    public static function minimumLength(): int
    {
        return self::MIN_LENGTH;
    }

    /**
     * A readable temporary password for a newly invited staff account.
     * The account is flagged must_change_password, so it survives one login.
     */
    public static function generateTemporary(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $password = '';

        for ($i = 0; $i < 14; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        // Guarantee the policy is satisfied regardless of what the loop drew.
        return $password . '#' . random_int(10, 99);
    }
}
