<?php

declare(strict_types=1);

namespace Aster\Application\Service;

use Aster\Domain\Entity\User;
use Aster\Domain\Enum\UserStatus;
use Aster\Domain\Exception\HttpException;
use Aster\Domain\Exception\ValidationException;
use Aster\Infrastructure\Persistence\AuditLogger;
use Aster\Infrastructure\Persistence\UserRepository;
use Aster\Infrastructure\Security\Csrf;
use Aster\Infrastructure\Security\PasswordHasher;
use Aster\Infrastructure\Security\RateLimiter;
use Aster\Infrastructure\Security\SessionManager;
use Aster\Infrastructure\Support\Config;
use Aster\Infrastructure\Support\Logger;
use SensitiveParameter;

/**
 * Staff authentication.
 *
 * Three layers of brute-force resistance, because each defeats a different
 * attack:
 *
 *  - Per-IP rate limit: stops one host hammering many accounts.
 *  - Per-account lockout: stops a botnet hammering one account.
 *  - Constant-ish response time: a missing account still runs a dummy verify,
 *    so timing cannot be used to enumerate valid staff emails.
 *
 * Every failure returns the same message regardless of cause. Telling an
 * attacker "no such user" versus "wrong password" hands them half the
 * credential for free.
 */
final readonly class AuthService
{
    private const string GENERIC_FAILURE = 'The email or password you entered is incorrect.';

    public function __construct(
        private UserRepository $users,
        private PasswordHasher $hasher,
        private SessionManager $session,
        private Csrf $csrf,
        private RateLimiter $limiter,
        private AuditLogger $audit,
        private Config $config,
        private Logger $logger,
    ) {
    }

    /**
     * Authenticate and establish a session.
     *
     * @throws ValidationException on bad credentials or a locked account
     * @throws HttpException       429 when the IP is rate limited
     */
    public function login(
        string $email,
        #[SensitiveParameter] string $password,
        string $ip,
        ?string $ipBinary,
        string $userAgent,
    ): User {
        $email = mb_strtolower(trim($email));

        // Keyed on IP alone, not IP+email: keying on both would let an
        // attacker get a fresh budget for every address they try.
        $ipKey = 'login:ip:' . $ip;

        if (!$this->limiter->attempt($ipKey, $this->config->loginMaxAttempts() * 4, 900)) {
            $this->logger->warning('Login rate limit exceeded', ['ip' => $ip]);

            throw HttpException::tooManyRequests($this->limiter->retryAfter($ipKey));
        }

        $credentials = $this->users->findCredentials($email);

        if ($credentials === null) {
            // Equalise timing against the real-account path.
            $this->hasher->burnTime();
            $this->recordFailure($email, $ip, 'unknown_account');

            throw ValidationException::single('email', self::GENERIC_FAILURE);
        }

        $userId = (int) $credentials['id'];

        if ($this->isLockedOut($credentials)) {
            $this->recordFailure($email, $ip, 'locked_out');

            throw ValidationException::single('email', sprintf(
                'This account is temporarily locked after repeated failed attempts. Please try again in %d minutes.',
                $this->config->loginLockoutMinutes(),
            ));
        }

        if (!$this->hasher->verify($password, (string) $credentials['password_hash'])) {
            $locked = $this->users->registerFailedLogin(
                $userId,
                $this->config->loginMaxAttempts(),
                $this->config->loginLockoutMinutes(),
            );

            $this->recordFailure($email, $ip, $locked ? 'locked_now' : 'bad_password', $userId);

            if ($locked) {
                $this->audit->record(
                    AuditLogger::LOCKED_OUT,
                    'user',
                    $userId,
                    'Account locked after repeated failed logins',
                );

                throw ValidationException::single('email', sprintf(
                    'Too many failed attempts. This account is locked for %d minutes.',
                    $this->config->loginLockoutMinutes(),
                ));
            }

            throw ValidationException::single('email', self::GENERIC_FAILURE);
        }

        // Correct password, but the account may still not be permitted in.
        $status = UserStatus::from((string) $credentials['status']);

        if (!$status->canLogin()) {
            $this->recordFailure($email, $ip, 'status_' . $status->value, $userId);

            throw ValidationException::single('email', $status === UserStatus::INVITED
                ? 'This account has not been activated yet. Please contact your administrator.'
                : 'This account has been suspended. Please contact your administrator.');
        }

        // Transparently upgrade the hash if the cost parameters were raised.
        if ($this->hasher->needsRehash((string) $credentials['password_hash'])) {
            $this->users->updatePassword($userId, $this->hasher->hash($password));
        }

        $this->users->registerSuccessfulLogin($userId, $ipBinary);
        $this->limiter->clear($ipKey);

        // Regenerates the session id, defeating fixation.
        $this->session->login($userId, SessionManager::fingerprint($userAgent, $ip));

        // A token minted pre-authentication must not remain valid after it.
        $this->csrf->rotate();

        $user = $this->users->findById($userId);

        if ($user === null) {
            throw HttpException::serverError();
        }

        $this->audit
            ->withActor($user, $ipBinary, $userAgent)
            ->record(AuditLogger::LOGIN, 'user', $userId, 'Signed in');

        return $user;
    }

    /** @param array<string, mixed> $credentials */
    private function isLockedOut(array $credentials): bool
    {
        $lockedUntil = $credentials['locked_until'] ?? null;

        if (!is_string($lockedUntil) || $lockedUntil === '') {
            return false;
        }

        return new \DateTimeImmutable($lockedUntil . ' UTC') > new \DateTimeImmutable();
    }

    private function recordFailure(string $email, string $ip, string $reason, ?int $userId = null): void
    {
        // The email is logged because a support request about a lockout is
        // unanswerable without it; the password never is, in any form.
        $this->logger->warning('Failed login attempt', [
            'email'  => $email,
            'ip'     => $ip,
            'reason' => $reason,
        ]);

        $this->audit->record(
            AuditLogger::LOGIN_FAILED,
            'user',
            $userId,
            sprintf('Failed login for %s (%s)', $email, $reason),
        );
    }

    public function logout(?User $user, ?string $ipBinary, string $userAgent): void
    {
        if ($user !== null) {
            $this->audit
                ->withActor($user, $ipBinary, $userAgent)
                ->record(AuditLogger::LOGOUT, 'user', $user->id, 'Signed out');
        }

        $this->session->destroy();
    }

    /** The signed-in user, or null. */
    public function currentUser(): ?User
    {
        $userId = $this->session->userId();

        if ($userId === null) {
            return null;
        }

        $user = $this->users->findById($userId);

        // The account was deleted or suspended mid-session; end it now
        // rather than letting the session outlive the authorisation.
        if ($user === null || !$user->canAuthenticate()) {
            $this->session->destroy();

            return null;
        }

        return $user;
    }

    /**
     * Change the signed-in user's own password.
     *
     * @throws ValidationException
     */
    public function changePassword(
        User $user,
        #[SensitiveParameter] string $currentPassword,
        #[SensitiveParameter] string $newPassword,
        #[SensitiveParameter] string $confirmation,
    ): void {
        $credentials = $this->users->findCredentialsById($user->id);

        if ($credentials === null || !$this->hasher->verify($currentPassword, (string) $credentials['password_hash'])) {
            throw ValidationException::single('current_password', 'Your current password is incorrect.');
        }

        if ($newPassword !== $confirmation) {
            throw ValidationException::single('password_confirmation', 'The two passwords do not match.');
        }

        if ($newPassword === $currentPassword) {
            throw ValidationException::single('password', 'Please choose a different password from your current one.');
        }

        PasswordHasher::assertStrong($newPassword);

        $this->users->updatePassword($user->id, $this->hasher->hash($newPassword));

        // Re-key the session: any parallel session established with the old
        // password should not survive a deliberate credential change.
        $this->session->regenerate();
        $this->csrf->rotate();

        $this->audit->record(AuditLogger::PASSWORD_CHANGED, 'user', $user->id, 'Changed own password');
    }

    /**
     * Require a permission or refuse the request.
     *
     * @throws HttpException 401 when signed out, 403 when under-privileged
     */
    public function authorise(?User $user, string $permission): User
    {
        if ($user === null) {
            throw HttpException::unauthorized();
        }

        if (!$user->can($permission)) {
            $this->logger->warning('Authorisation denied', [
                'user_id'    => $user->id,
                'role'       => $user->role->value,
                'permission' => $permission,
            ]);

            throw HttpException::forbidden();
        }

        return $user;
    }
}
