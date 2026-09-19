<?php

declare(strict_types=1);

namespace MediCareMini\Infrastructure\Security;

use MediCareMini\Infrastructure\Support\Config;

/**
 * Hardened session lifecycle.
 *
 * Three protections matter here and are easy to get wrong:
 *
 *  1. Cookie flags are set before the session starts, not after. Setting them
 *     afterwards has no effect on the cookie already emitted.
 *  2. Two independent timeouts run: an idle timeout that slides with activity
 *     and an absolute cap that does not. A single idle timeout lets a stolen
 *     session live forever as long as it keeps being used.
 *  3. The id is regenerated on login and on privilege change, which is what
 *     defeats session fixation.
 */
final class SessionManager
{
    private const string KEY_USER_ID    = '_auth_user_id';
    private const string KEY_LAST_SEEN  = '_auth_last_seen';
    private const string KEY_STARTED_AT = '_auth_started_at';
    private const string KEY_FINGERPRINT = '_auth_fingerprint';
    private const string KEY_FLASH      = '_flash';
    private const string KEY_FLASH_NEXT = '_flash_next';

    // Patient portal identity - a completely separate slot from staff
    // identity above, storage-level as well as name-level. A patient must
    // never be able to satisfy a staff `can:` gate, and the reverse: this
    // is what keeps a patient session from ever being mistaken for one by
    // code that only ever checks the KEY_USER_ID family.
    private const string KEY_PATIENT_ID          = '_portal_patient_id';
    private const string KEY_PATIENT_LAST_SEEN   = '_portal_last_seen';
    private const string KEY_PATIENT_STARTED_AT  = '_portal_started_at';
    private const string KEY_PATIENT_FINGERPRINT = '_portal_fingerprint';

    private bool $started = false;

    public function __construct(private readonly Config $config)
    {
    }

    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;

            return;
        }

        session_name($this->config->sessionName());

        session_set_cookie_params([
            'lifetime' => 0,                                  // browser-session cookie
            'path'     => '/',
            'domain'   => '',
            'secure'   => $this->config->sessionSecure(),
            'httponly' => true,                               // unreadable from JavaScript
            'samesite' => $this->config->sessionSameSite(),   // blocks cross-site POSTs
        ]);

        // Never accept a session id supplied in the URL - that is how session
        // ids end up in Referer headers and server logs.
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_maxlifetime', (string) $this->config->sessionAbsoluteSeconds());

        session_start();

        $this->started = true;

        $this->rotateFlash();
    }

    /**
     * Enforce both timeouts and the client fingerprint.
     *
     * @return bool false when the session was destroyed and the user must
     *              sign in again.
     */
    public function validate(string $fingerprint): bool
    {
        return $this->validateIdentity(
            self::KEY_USER_ID, self::KEY_LAST_SEEN, self::KEY_STARTED_AT, self::KEY_FINGERPRINT,
            $fingerprint,
        );
    }

    /** Same contract as validate(), scoped to the patient portal's own identity slot. */
    public function validatePatientSession(string $fingerprint): bool
    {
        return $this->validateIdentity(
            self::KEY_PATIENT_ID, self::KEY_PATIENT_LAST_SEEN, self::KEY_PATIENT_STARTED_AT, self::KEY_PATIENT_FINGERPRINT,
            $fingerprint,
        );
    }

    private function validateIdentity(
        string $idKey,
        string $lastSeenKey,
        string $startedAtKey,
        string $fingerprintKey,
        string $fingerprint,
    ): bool {
        if (!$this->has($idKey)) {
            return true; // Anonymous sessions have nothing to expire.
        }

        $now       = time();
        $lastSeen  = (int) $this->get($lastSeenKey, 0);
        $startedAt = (int) $this->get($startedAtKey, 0);

        $idleExpired     = $lastSeen > 0
            && ($now - $lastSeen) > $this->config->sessionIdleSeconds();
        $absoluteExpired = $startedAt > 0
            && ($now - $startedAt) > $this->config->sessionAbsoluteSeconds();

        // A changed fingerprint means the cookie is being replayed from a
        // different browser or network - treat it as theft, not as drift.
        $stored = $this->get($fingerprintKey);
        $hijacked = is_string($stored) && !hash_equals($stored, $fingerprint);

        if ($idleExpired || $absoluteExpired || $hijacked) {
            $this->destroy();

            return false;
        }

        $this->set($lastSeenKey, $now);

        return true;
    }

    /** Bind a freshly authenticated user to this session. */
    public function login(int $userId, string $fingerprint): void
    {
        // Regenerate BEFORE writing identity, so a fixated id is discarded.
        $this->regenerate();

        $this->set(self::KEY_USER_ID, $userId);
        $this->set(self::KEY_STARTED_AT, time());
        $this->set(self::KEY_LAST_SEEN, time());
        $this->set(self::KEY_FINGERPRINT, $fingerprint);
    }

    public function userId(): ?int
    {
        $id = $this->get(self::KEY_USER_ID);

        return is_int($id) ? $id : null;
    }

    public function isAuthenticated(): bool
    {
        return $this->userId() !== null;
    }

    /** Bind a freshly authenticated patient to this session - see the KEY_PATIENT_* docblock above. */
    public function loginPatient(int $patientId, string $fingerprint): void
    {
        $this->regenerate();

        $this->set(self::KEY_PATIENT_ID, $patientId);
        $this->set(self::KEY_PATIENT_STARTED_AT, time());
        $this->set(self::KEY_PATIENT_LAST_SEEN, time());
        $this->set(self::KEY_PATIENT_FINGERPRINT, $fingerprint);
    }

    public function patientId(): ?int
    {
        $id = $this->get(self::KEY_PATIENT_ID);

        return is_int($id) ? $id : null;
    }

    public function isPatientAuthenticated(): bool
    {
        return $this->patientId() !== null;
    }

    public function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];

        // Expire the cookie itself, not just the server-side data.
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
        $this->started = false;
    }

    // --- Generic storage ------------------------------------------------

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    // --- Flash messages -------------------------------------------------

    /**
     * Queue a message for the NEXT request, which is where a redirect lands.
     *
     * @param 'success'|'error'|'warning'|'info' $type
     */
    public function flash(string $type, string $message): void
    {
        $_SESSION[self::KEY_FLASH_NEXT][] = ['type' => $type, 'message' => $message];
    }

    /** @return list<array{type:string, message:string}> */
    public function flashMessages(): array
    {
        return $_SESSION[self::KEY_FLASH] ?? [];
    }

    /** Preserve form input across a validation redirect. */
    public function flashInput(array $input): void
    {
        // Never echo a password back into a re-rendered form.
        unset($input['password'], $input['password_confirmation'], $input['current_password'], $input['_token']);

        $_SESSION[self::KEY_FLASH_NEXT . '_input'] = $input;
    }

    public function oldInput(): array
    {
        return $_SESSION[self::KEY_FLASH . '_input'] ?? [];
    }

    public function flashErrors(array $errors): void
    {
        $_SESSION[self::KEY_FLASH_NEXT . '_errors'] = $errors;
    }

    public function errors(): array
    {
        return $_SESSION[self::KEY_FLASH . '_errors'] ?? [];
    }

    /**
     * Promote the queued bucket to the readable one and clear the queue.
     * Runs once per request, immediately after the session starts.
     */
    private function rotateFlash(): void
    {
        foreach (['', '_input', '_errors'] as $suffix) {
            $_SESSION[self::KEY_FLASH . $suffix] = $_SESSION[self::KEY_FLASH_NEXT . $suffix] ?? [];
            unset($_SESSION[self::KEY_FLASH_NEXT . $suffix]);
        }
    }

    /**
     * A coarse client fingerprint: user agent plus the network portion of the
     * IP. The last octet is dropped so a mobile user roaming between towers
     * is not logged out mid-shift, while a session replayed from a different
     * network still fails the check.
     */
    public static function fingerprint(string $userAgent, string $ip): string
    {
        $network = $ip;

        if (str_contains($ip, '.')) {
            $parts = explode('.', $ip);
            array_pop($parts);
            $network = implode('.', $parts);
        } elseif (str_contains($ip, ':')) {
            $parts   = explode(':', $ip);
            $network = implode(':', array_slice($parts, 0, 4));
        }

        return hash('sha256', $userAgent . '|' . $network);
    }
}
