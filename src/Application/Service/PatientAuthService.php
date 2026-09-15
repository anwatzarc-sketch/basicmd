<?php

declare(strict_types=1);

namespace Aster\Application\Service;

use Aster\Domain\Entity\Patient;
use Aster\Domain\Exception\HttpException;
use Aster\Domain\Exception\ValidationException;
use Aster\Domain\Repository\PatientAccountRepositoryInterface;
use Aster\Domain\Repository\PatientRepositoryInterface;
use Aster\Infrastructure\Persistence\AuditLogger;
use Aster\Infrastructure\Security\PasswordHasher;
use Aster\Infrastructure\Security\RateLimiter;
use Aster\Infrastructure\Security\SessionManager;
use Aster\Infrastructure\Support\Config;
use Aster\Infrastructure\Support\Logger;
use SensitiveParameter;

/**
 * Patient portal authentication (FRS 5.3, 10.6, 11.1).
 *
 * Deliberately mirrors AuthService's shape and its defences - per-IP rate
 * limiting, a generic failure message regardless of cause, and a dummy
 * password verify on a missing account so response time cannot be used to
 * enumerate valid PIDs. What it does NOT carry over is per-account
 * lockout: patient_accounts (FRS 5.3's own supplied DDL) has no
 * failed_attempts/locked_until columns the way `users` does, and the FRS
 * scope directive is to preserve the given schema rather than add to it
 * without a compelling invariant - per-IP limiting plus the timing
 * defence is the compatible subset of AuthService's protection this
 * schema can actually support.
 *
 * Login identifies the patient by PID, not phone or name: PID is the one
 * field patient_accounts' own schema has no ambiguity about - it carries
 * a UNIQUE constraint on patients.pid, unlike phone_number.
 */
final readonly class PatientAuthService
{
    private const string GENERIC_FAILURE = 'The patient ID or password you entered is incorrect.';

    public function __construct(
        private PatientRepositoryInterface $patients,
        private PatientAccountRepositoryInterface $accounts,
        private PasswordHasher $hasher,
        private SessionManager $session,
        private RateLimiter $limiter,
        private AuditLogger $audit,
        private Config $config,
        private Logger $logger,
    ) {
    }

    /**
     * Authenticate and establish a portal session.
     *
     * @throws ValidationException on bad credentials or an inactive account
     * @throws HttpException       429 when the IP is rate limited
     */
    public function login(
        string $pid,
        #[SensitiveParameter] string $password,
        string $ip,
        ?string $ipBinary,
        string $userAgent,
    ): Patient {
        $pid = mb_strtoupper(trim($pid));

        $ipKey = 'portal_login:ip:' . $ip;

        if (!$this->limiter->attempt($ipKey, $this->config->loginMaxAttempts() * 4, 900)) {
            $this->logger->warning('Patient portal login rate limit exceeded', ['ip' => $ip]);

            throw HttpException::tooManyRequests($this->limiter->retryAfter($ipKey));
        }

        $credentials = $this->accounts->findCredentialsByPid($pid);

        if ($credentials === null) {
            // Equalise timing against the real-account path.
            $this->hasher->burnTime();
            $this->recordFailure($pid, $ip, 'unknown_account');

            throw ValidationException::single('pid', self::GENERIC_FAILURE);
        }

        if (!$this->hasher->verify($password, (string) $credentials['password_hash'])) {
            $this->recordFailure($pid, $ip, 'bad_password');

            throw ValidationException::single('pid', self::GENERIC_FAILURE);
        }

        if (!(bool) $credentials['is_active']) {
            $this->recordFailure($pid, $ip, 'inactive_account');

            throw ValidationException::single('pid', 'This portal account has been deactivated. Please contact the clinic.');
        }

        $accountId = (int) $credentials['id'];
        $patientId = (int) $credentials['patient_id'];

        $patient = $this->patients->findById($patientId);

        if ($patient === null) {
            throw HttpException::serverError();
        }

        $this->accounts->recordLogin($accountId);
        $this->limiter->clear($ipKey);

        // Regenerates the session id, defeating fixation.
        $this->session->loginPatient($patientId, SessionManager::fingerprint($userAgent, $ip));

        $this->audit
            ->asAnonymous(sprintf('%s (%s)', $patient->fullName(), $patient->pid->value), $ipBinary, $userAgent)
            ->record(AuditLogger::PORTAL_LOGIN, 'patient', $patientId, 'Signed in to the patient portal');

        return $patient;
    }

    private function recordFailure(string $pid, string $ip, string $reason): void
    {
        $this->logger->warning('Failed patient portal login attempt', [
            'pid'    => $pid,
            'ip'     => $ip,
            'reason' => $reason,
        ]);

        $this->audit->record(
            AuditLogger::PORTAL_LOGIN_FAILED,
            'patient',
            null,
            sprintf('Failed portal login for %s (%s)', $pid, $reason),
        );
    }

    public function logout(?Patient $patient, ?string $ipBinary, string $userAgent): void
    {
        if ($patient !== null) {
            $this->audit
                ->asAnonymous(sprintf('%s (%s)', $patient->fullName(), $patient->pid->value), $ipBinary, $userAgent)
                ->record(AuditLogger::PORTAL_LOGOUT, 'patient', $patient->id, 'Signed out of the patient portal');
        }

        $this->session->destroy();
    }

    /** The signed-in patient, or null. */
    public function currentPatient(): ?Patient
    {
        $patientId = $this->session->patientId();

        if ($patientId === null) {
            return null;
        }

        $patient = $this->patients->findById($patientId);

        if ($patient === null) {
            // The record was deleted mid-session; end it rather than let
            // the session outlive the patient it belongs to.
            $this->session->destroy();

            return null;
        }

        return $patient;
    }

    /**
     * Give a patient portal access, or reset it if they already have some.
     *
     * A receptionist-facing action, not patient self-service - the FRS
     * defines no self-registration flow for the portal, only the
     * dashboard route itself (10.6) and the account rules (5.3). Find-or-
     * create mirrors UserController's own "invite" pattern: a temporary
     * password is generated here, shown to staff exactly once, and never
     * logged or emailed - patient_accounts has no must_change_password
     * column (unlike `users`), so unlike a staff invite this password is
     * NOT forced to be changed at first use; staff are expected to hand
     * it to the patient through a secure channel.
     *
     * @return string the plaintext temporary password, for the caller to
     *                display exactly once
     */
    public function provisionAccess(int $patientId): string
    {
        $temporary = PasswordHasher::generateTemporary();
        $hash      = $this->hasher->hash($temporary);

        $existing = $this->accounts->findByPatientId($patientId);

        if ($existing !== null) {
            $this->accounts->updatePasswordHash($existing->id, $hash);
        } else {
            $this->accounts->create([
                'patient_id'    => $patientId,
                'password_hash' => $hash,
                'is_active'     => 1,
            ]);
        }

        $this->audit->record(
            AuditLogger::PORTAL_ACCESS_PROVISIONED,
            'patient',
            $patientId,
            $existing !== null ? 'Reset patient portal password' : 'Provisioned patient portal access',
        );

        return $temporary;
    }
}
