<?php

declare(strict_types=1);

namespace Aster\Infrastructure\Security;

/**
 * Synchroniser-token CSRF protection.
 *
 * One token per session, compared with hash_equals so the check is not
 * timing-dependent. Tokens are per-session rather than per-form: per-form
 * tokens break the back button and multi-tab use, which on a booking form
 * costs real conversions, and the session token is equally effective as long
 * as it is never exposed to another origin.
 *
 * The public booking and contact forms are protected too. They are anonymous,
 * but a forged cross-site POST can still flood the appointment table.
 */
final class Csrf
{
    private const string SESSION_KEY = '_csrf_token';
    private const string FIELD_NAME  = '_token';
    private const string HEADER_NAME = 'X-CSRF-Token';
    private const int    TOKEN_BYTES = 32;

    public function __construct(private readonly SessionManager $session)
    {
    }

    /** Current token, minted on first use. */
    public function token(): string
    {
        $token = $this->session->get(self::SESSION_KEY);

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(self::TOKEN_BYTES));
            $this->session->set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    /**
     * Issue a fresh token, discarding the old one.
     * Called on login and logout so a token minted before authentication
     * cannot be replayed afterwards.
     */
    public function rotate(): string
    {
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        $this->session->set(self::SESSION_KEY, $token);

        return $token;
    }

    public function isValid(?string $candidate): bool
    {
        $expected = $this->session->get(self::SESSION_KEY);

        if (!is_string($expected) || $expected === '' || !is_string($candidate) || $candidate === '') {
            return false;
        }

        return hash_equals($expected, $candidate);
    }

    /** Ready-to-echo hidden input for a form. */
    public function field(): string
    {
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            self::FIELD_NAME,
            htmlspecialchars($this->token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        );
    }

    /** Meta tag so fetch()/XHR can read the token for the header form. */
    public function metaTag(): string
    {
        return sprintf(
            '<meta name="csrf-token" content="%s">',
            htmlspecialchars($this->token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        );
    }

    public static function fieldName(): string
    {
        return self::FIELD_NAME;
    }

    public static function headerName(): string
    {
        return self::HEADER_NAME;
    }
}
