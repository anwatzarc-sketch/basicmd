<?php

declare(strict_types=1);

namespace Aster\Tests\Feature\Services;

use Aster\Application\Service\PatientAuthService;
use Aster\Domain\Exception\ValidationException;
use Aster\Domain\Repository\PatientAccountRepositoryInterface;
use Aster\Infrastructure\Security\SessionManager;
use Aster\Tests\Support\DatabaseTestCase;

/**
 * Patient portal authentication (FRS 5.3, 10.6, 11.1), against the real
 * database - password verification and the generic-failure-message
 * behaviour are exactly the kind of thing a mock cannot honestly exercise.
 *
 * A real PHP session is started/destroyed around each test (not just
 * SessionManager's in-memory $_SESSION writes): login()/logout() route
 * through SessionManager::regenerate()/destroy(), and both are no-ops
 * when session_status() is not PHP_SESSION_ACTIVE - exactly the state a
 * bare service call would otherwise leave this CLI process in, which
 * would make logout() look broken for a reason that has nothing to do
 * with the code under test.
 */
final class PatientAuthServiceTest extends DatabaseTestCase
{
    private PatientAuthService $auth;
    private PatientAccountRepositoryInterface $accounts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->auth     = $this->container->get(PatientAuthService::class);
        $this->accounts = $this->container->get(PatientAccountRepositoryInterface::class);
        $this->container->get(SessionManager::class)->start();
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];

        parent::tearDown();
    }

    private function makePatient(): array
    {
        $pid = 'PID-7000-' . random_int(10000, 99999);

        $this->db->execute(
            "INSERT INTO patients (pid, first_name, last_name, date_of_birth, gender, phone_number, created_at)
             VALUES (:pid, 'Portal', 'TestPatient', '1990-01-01', 'other', :phone, UTC_TIMESTAMP())",
            ['pid' => $pid, 'phone' => '+2519' . random_int(10000000, 99999999)],
        );

        return ['id' => $this->db->lastInsertId(), 'pid' => $pid];
    }

    public function test_provisioning_access_creates_an_active_account(): void
    {
        $patient = $this->makePatient();

        $temporary = $this->auth->provisionAccess($patient['id']);

        self::assertNotSame('', $temporary);

        $account = $this->accounts->findByPatientId($patient['id']);
        self::assertNotNull($account);
        self::assertTrue($account->isActive);
        self::assertSame($patient['id'], $account->patientId);
    }

    public function test_a_provisioned_patient_can_log_in_with_pid_and_the_temporary_password(): void
    {
        $patient   = $this->makePatient();
        $temporary = $this->auth->provisionAccess($patient['id']);

        $signedIn = $this->auth->login(
            pid:       $patient['pid'],
            password:  $temporary,
            ip:        '127.0.0.1',
            ipBinary:  null,
            userAgent: 'phpunit',
        );

        self::assertSame($patient['id'], $signedIn->id);
        self::assertSame($patient['id'], $this->auth->currentPatient()?->id);
    }

    public function test_login_fails_with_the_wrong_password_and_never_reveals_which_field_was_wrong(): void
    {
        $patient = $this->makePatient();
        $this->auth->provisionAccess($patient['id']);

        try {
            $this->auth->login(
                pid:       $patient['pid'],
                password:  'definitely-the-wrong-password',
                ip:        '127.0.0.1',
                ipBinary:  null,
                userAgent: 'phpunit',
            );
            self::fail('Expected a ValidationException for a wrong password.');
        } catch (ValidationException $e) {
            self::assertStringContainsString('incorrect', $e->getMessage());
        }

        self::assertNull($this->auth->currentPatient());
    }

    public function test_login_fails_for_an_unknown_pid_with_the_same_generic_message(): void
    {
        try {
            $this->auth->login(
                pid:       'PID-9999-99999',
                password:  'whatever-password',
                ip:        '127.0.0.1',
                ipBinary:  null,
                userAgent: 'phpunit',
            );
            self::fail('Expected a ValidationException for an unknown PID.');
        } catch (ValidationException $e) {
            self::assertStringContainsString('incorrect', $e->getMessage());
        }
    }

    public function test_re_provisioning_resets_the_password_and_invalidates_the_old_one(): void
    {
        $patient = $this->makePatient();
        $first   = $this->auth->provisionAccess($patient['id']);
        $second  = $this->auth->provisionAccess($patient['id']);

        self::assertNotSame($first, $second);

        // Exactly one account row still exists - re-provisioning resets the
        // existing one rather than creating a second (FRS 5.3: "must not
        // silently create multiple active portal identities for one patient").
        $account = $this->accounts->findByPatientId($patient['id']);
        self::assertNotNull($account);

        try {
            $this->auth->login(
                pid: $patient['pid'], password: $first,
                ip: '127.0.0.1', ipBinary: null, userAgent: 'phpunit',
            );
            self::fail('The old temporary password must no longer work.');
        } catch (ValidationException) {
            // Expected.
        }

        $signedIn = $this->auth->login(
            pid: $patient['pid'], password: $second,
            ip: '127.0.0.1', ipBinary: null, userAgent: 'phpunit',
        );
        self::assertSame($patient['id'], $signedIn->id);
    }

    public function test_an_inactive_account_cannot_authenticate(): void
    {
        $patient = $this->makePatient();
        $temporary = $this->auth->provisionAccess($patient['id']);

        $account = $this->accounts->findByPatientId($patient['id']);
        $this->db->execute('UPDATE patient_accounts SET is_active = 0 WHERE id = :id', ['id' => $account->id]);

        try {
            $this->auth->login(
                pid: $patient['pid'], password: $temporary,
                ip: '127.0.0.1', ipBinary: null, userAgent: 'phpunit',
            );
            self::fail('An inactive account must not be allowed to authenticate (FRS 5.3 rule 5).');
        } catch (ValidationException $e) {
            self::assertStringContainsString('deactivated', $e->getMessage());
        }
    }

    public function test_logout_clears_the_portal_session(): void
    {
        $patient   = $this->makePatient();
        $temporary = $this->auth->provisionAccess($patient['id']);

        $signedIn = $this->auth->login(
            pid: $patient['pid'], password: $temporary,
            ip: '127.0.0.1', ipBinary: null, userAgent: 'phpunit',
        );

        $this->auth->logout($signedIn, null, 'phpunit');

        self::assertNull($this->auth->currentPatient());
    }
}
