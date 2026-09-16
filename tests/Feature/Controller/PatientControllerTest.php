<?php

declare(strict_types=1);

namespace Aster\Tests\Feature\Controller;

use Aster\Application\Service\PatientAuthService;
use Aster\Domain\Entity\User;
use Aster\Domain\Enum\DiagnosticCategory;
use Aster\Domain\Enum\LedgerCategory;
use Aster\Domain\Enum\LedgerMode;
use Aster\Domain\Enum\ReceivablePaymentMethod;
use Aster\Domain\Enum\UserRole;
use Aster\Domain\Enum\VisitType;
use Aster\Domain\Exception\HttpException;
use Aster\Domain\Repository\ClinicalNoteRepositoryInterface;
use Aster\Domain\Repository\DiagnosticOrderRepositoryInterface;
use Aster\Domain\Repository\EncounterRepositoryInterface;
use Aster\Domain\Repository\LedgerRepositoryInterface;
use Aster\Domain\Repository\PrescriptionRepositoryInterface;
use Aster\Domain\Repository\ReceivablePaymentRepositoryInterface;
use Aster\Domain\Services\EncounterService;
use Aster\Domain\Services\PatientDeduplicationService;
use Aster\Infrastructure\Persistence\AuditLogger;
use Aster\Infrastructure\Persistence\PatientRepository;
use Aster\Infrastructure\Persistence\UserRepository;
use Aster\Infrastructure\Security\SessionManager;
use Aster\Infrastructure\Support\Config;
use Aster\Presentation\Controller\Admin\PatientController;
use Aster\Presentation\Http\Request;
use Aster\Presentation\Http\Response;
use Aster\Presentation\View\View;
use Aster\Tests\Support\DatabaseTestCase;

/**
 * PatientController's write actions and the Financial panel's arithmetic,
 * exercised directly against the real container (no HTTP layer, no mocks) -
 * the same reasoning DatabaseTestCase's own docblock gives for the whole
 * suite: row locks, unique-index collisions and the ledger's SUM()
 * invariant are exactly the properties a mock cannot verify.
 */
final class PatientControllerTest extends DatabaseTestCase
{
    private const int ACTOR_ID = 1; // admin@astermedical.et, seeded super_admin

    private PatientController $controller;
    private PatientRepository $patients;
    private EncounterRepositoryInterface $encounters;
    private ClinicalNoteRepositoryInterface $clinicalNotes;
    private DiagnosticOrderRepositoryInterface $diagnostics;
    private PrescriptionRepositoryInterface $prescriptions;
    private LedgerRepositoryInterface $ledger;
    private ReceivablePaymentRepositoryInterface $receivablePayments;
    private UserRepository $users;

    protected function setUp(): void
    {
        parent::setUp();

        $this->patients           = $this->container->get(PatientRepository::class);
        $this->encounters         = $this->container->get(EncounterRepositoryInterface::class);
        $this->clinicalNotes      = $this->container->get(ClinicalNoteRepositoryInterface::class);
        $this->diagnostics        = $this->container->get(DiagnosticOrderRepositoryInterface::class);
        $this->prescriptions      = $this->container->get(PrescriptionRepositoryInterface::class);
        $this->ledger             = $this->container->get(LedgerRepositoryInterface::class);
        $this->receivablePayments = $this->container->get(ReceivablePaymentRepositoryInterface::class);
        $this->users              = $this->container->get(UserRepository::class);

        $this->controller = new PatientController(
            $this->container->get(View::class),
            $this->container->get(SessionManager::class),
            $this->container->get(Config::class),
            $this->patients,
            $this->container->get(PatientDeduplicationService::class),
            $this->encounters,
            $this->container->get(PatientAuthService::class),
            $this->clinicalNotes,
            $this->diagnostics,
            $this->prescriptions,
            $this->ledger,
            $this->receivablePayments,
            $this->container->get(AuditLogger::class),
        );
    }

    private function makeUser(UserRole $role): User
    {
        $id = $this->users->create(
            fullName:     'Test ' . $role->value,
            email:        $role->value . '-' . bin2hex(random_bytes(4)) . '@test.invalid',
            passwordHash: '$argon2id$v=19$m=1024,t=1,p=1$c29tZXNhbHQ$aGFzaA',
            role:         $role,
        );

        $user = $this->users->findById($id);
        self::assertNotNull($user);

        return $user;
    }

    private function makePatient(): int
    {
        $this->db->execute(
            "INSERT INTO patients (pid, first_name, last_name, date_of_birth, gender, phone_number, created_at)
             VALUES (:pid, 'Detail', 'TestPatient', '1985-01-01', 'other', :phone, UTC_TIMESTAMP())",
            ['pid' => 'PID-8000-' . random_int(10000, 99999), 'phone' => '+2519' . random_int(10000000, 99999999)],
        );

        return (int) $this->db->lastInsertId();
    }

    private function makeEncounter(int $patientId): int
    {
        /** @var EncounterService $encounterService */
        $encounterService = $this->container->get(EncounterService::class);

        return $encounterService->startEncounter($patientId, VisitType::OPD, null)->id;
    }

    private function charge(int $encounterId, float $amount, ?int $parentEntryId = null): int
    {
        return $this->ledger->create([
            'encounter_id'    => $encounterId,
            'accountant_id'   => self::ACTOR_ID,
            'category'        => LedgerCategory::CONSULTATION->value,
            'cost_entry'      => 'Test charge',
            'unit'            => 1,
            'per_unit_cost'   => number_format($amount, 2, '.', ''),
            'reason'          => 'Test',
            'mode'            => LedgerMode::OPD->value,
            'parent_entry_id' => $parentEntryId,
        ]);
    }

    private function pay(int $encounterId, float $amount): int
    {
        return $this->receivablePayments->create([
            'receipt_id'     => 'RCT-' . date('Ymd') . '-' . str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'encounter_id'   => $encounterId,
            'accountant_id'  => self::ACTOR_ID,
            'amount_paid'    => number_format($amount, 2, '.', ''),
            'payment_method' => ReceivablePaymentMethod::CASH->value,
        ]);
    }

    /** Simulates what Authenticate/Router already do for a real request. */
    private function actingAs(User $user, string $method, array $post, array $attributes): Request
    {
        $GLOBALS['aster_current_user'] = $user;

        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI']    = '/test';
        $_POST                     = $post;
        $_GET                      = [];

        return Request::capture()->withAttributes($attributes);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['aster_current_user']);
        $_POST = [];
        $_GET  = [];

        parent::tearDown();
    }

    // -----------------------------------------------------------------
    //  Financial computation (spec §5) - a correction row nets to the
    //  expected SUM(), and Outstanding reflects Charges - Payments
    //  correctly across a MULTI-encounter patient.
    // -----------------------------------------------------------------

    public function test_a_correction_row_nets_the_original_charge_to_zero(): void
    {
        $patientId   = $this->makePatient();
        $encounterId = $this->makeEncounter($patientId);

        $originalId = $this->charge($encounterId, 300.00);
        $this->charge($encounterId, -300.00, $originalId); // the correction

        self::assertTrue($this->ledger->sumForPatient($patientId)->isZero());
    }

    public function test_outstanding_balance_sums_charges_and_payments_across_multiple_encounters(): void
    {
        $patientId = $this->makePatient();

        $encounterA = $this->makeEncounter($patientId);
        $encounterB = $this->makeEncounter($patientId);

        $this->charge($encounterA, 500.00);
        $this->charge($encounterB, 250.00);
        $this->pay($encounterA, 200.00);

        $charges = $this->ledger->sumForPatient($patientId);
        $paid    = $this->receivablePayments->sumForPatient($patientId);
        $outstanding = $charges->subtract($paid);

        self::assertSame(750.0, $charges->toMajor());
        self::assertSame(200.0, $paid->toMajor());
        self::assertSame(550.0, $outstanding->toMajor());
    }

    // -----------------------------------------------------------------
    //  Clinical notes are append-only end to end through this UI.
    // -----------------------------------------------------------------

    public function test_clinical_note_repository_exposes_no_update_path(): void
    {
        $methods = array_map(
            static fn (\ReflectionMethod $m): string => $m->getName(),
            (new \ReflectionClass(ClinicalNoteRepositoryInterface::class))->getMethods(),
        );

        self::assertNotContains('update', $methods);
        self::assertContains('create', $methods);
    }

    public function test_a_correction_to_a_note_is_a_new_row_not_an_edit(): void
    {
        $physician   = $this->makeUser(UserRole::PHYSICIAN);
        $patientId   = $this->makePatient();
        $encounterId = $this->makeEncounter($patientId);

        $request = $this->actingAs($physician, 'POST', [
            'encounter_id' => (string) $encounterId,
            'note_type'    => 'progress_note',
            'content'      => 'Original observation',
        ], ['id' => (string) $patientId]);

        $response = $this->controller->postClinicalNote($request);
        self::assertSame(302, $response->status());

        $request2 = $this->actingAs($physician, 'POST', [
            'encounter_id' => (string) $encounterId,
            'note_type'    => 'progress_note',
            'content'      => 'Corrected observation',
        ], ['id' => (string) $patientId]);

        $this->controller->postClinicalNote($request2);

        $notes = $this->clinicalNotes->forPatient($patientId);
        self::assertCount(2, $notes, 'a correction is a second note, the first is never touched');
    }

    // -----------------------------------------------------------------
    //  A Nurse cannot create a note outside vitals/triage - rejected,
    //  never silently created as some other type.
    // -----------------------------------------------------------------

    public function test_nurse_cannot_create_a_progress_note(): void
    {
        $nurse       = $this->makeUser(UserRole::NURSE);
        $patientId   = $this->makePatient();
        $encounterId = $this->makeEncounter($patientId);

        $request = $this->actingAs($nurse, 'POST', [
            'encounter_id' => (string) $encounterId,
            'note_type'    => 'progress_note',
            'content'      => 'Should never be saved',
        ], ['id' => (string) $patientId]);

        $response = $this->controller->postClinicalNote($request);

        self::assertSame(302, $response->status(), 'rejected via a redirect, not a fatal error');
        self::assertSame([], $this->clinicalNotes->forPatient($patientId), 'nothing was written');
    }

    public function test_nurse_can_create_a_vitals_note(): void
    {
        $nurse       = $this->makeUser(UserRole::NURSE);
        $patientId   = $this->makePatient();
        $encounterId = $this->makeEncounter($patientId);

        $request = $this->actingAs($nurse, 'POST', [
            'encounter_id' => (string) $encounterId,
            'note_type'    => 'vitals',
            'content'      => 'BP 120/80, HR 72',
            'heart_rate'   => '72',
        ], ['id' => (string) $patientId]);

        $response = $this->controller->postClinicalNote($request);

        self::assertSame(302, $response->status());
        self::assertCount(1, $this->clinicalNotes->forPatient($patientId));
    }

    // -----------------------------------------------------------------
    //  A Lab Technician's diagnostic queue never includes Imaging/PACS.
    // -----------------------------------------------------------------

    public function test_lab_technicians_query_never_returns_imaging_or_pacs_orders(): void
    {
        $physician   = $this->makeUser(UserRole::PHYSICIAN);
        $labTech     = $this->makeUser(UserRole::LAB_TECHNICIAN);
        $patientId   = $this->makePatient();
        $encounterId = $this->makeEncounter($patientId);

        foreach ([DiagnosticCategory::LAB, DiagnosticCategory::IMAGING, DiagnosticCategory::PACS] as $category) {
            $this->diagnostics->create([
                'encounter_id'          => $encounterId,
                'ordering_physician_id' => $physician->id,
                'category'              => $category->value,
                'test_code'             => 'T-' . $category->value,
                'test_name'             => 'Test ' . $category->value,
                'icd_code'              => 'Z00',
                'status'                => 'ORDERED',
            ]);
        }

        $scope = \Aster\Presentation\Support\PatientDetailAccess::diagnosticCategoryScope($labTech);
        $orders = $this->diagnostics->forPatient($patientId, 50, $scope);

        self::assertCount(1, $orders);
        self::assertSame(DiagnosticCategory::LAB, $orders[0]->category);

        // Same guarantee restated against the unscoped physician view, so
        // the test also proves the scope is what changed the result, not
        // an accident of only one order existing.
        $unscoped = $this->diagnostics->forPatient($patientId, 50, null);
        self::assertCount(3, $unscoped);
    }

    public function test_lab_technician_cannot_place_an_order_even_for_lab(): void
    {
        $labTech     = $this->makeUser(UserRole::LAB_TECHNICIAN);
        $patientId   = $this->makePatient();
        $encounterId = $this->makeEncounter($patientId);

        $request = $this->actingAs($labTech, 'POST', [
            'encounter_id' => (string) $encounterId,
            'category'     => 'Lab',
            'test_code'    => 'CBC',
            'test_name'    => 'Complete Blood Count',
            'icd_code'     => 'Z00',
        ], ['id' => (string) $patientId]);

        $this->expectException(HttpException::class);

        try {
            $this->controller->postDiagnosticOrder($request);
        } catch (HttpException $e) {
            self::assertSame(403, $e->statusCode);
            self::assertSame([], $this->diagnostics->forEncounter($encounterId), 'nothing was written');

            throw $e;
        }
    }

    // -----------------------------------------------------------------
    //  Permission isolation: a role lacking the specific right is
    //  rejected outright (403), never silently given an empty/no-op
    //  success. No care-team/assignment table exists in this schema
    //  (flagged in the implementation plan) - any staff user holding
    //  the resource permission may act on any patient, so this is the
    //  isolation boundary that actually exists to test: permission,
    //  not per-patient assignment.
    // -----------------------------------------------------------------

    public function test_a_receptionist_cannot_dispense_a_prescription(): void
    {
        $receptionist = $this->makeUser(UserRole::RECEPTIONIST);
        $physician    = $this->makeUser(UserRole::PHYSICIAN);
        $patientId    = $this->makePatient();
        $encounterId  = $this->makeEncounter($patientId);

        $rxId = $this->prescriptions->create([
            'encounter_id'    => $encounterId,
            'prescriber_id'   => $physician->id,
            'icd_code'        => 'Z00',
            'medication_name' => 'Amoxicillin',
            'dosage'          => '500mg',
            'frequency'       => 'Twice daily',
            'duration_days'   => 7,
        ]);

        $request = $this->actingAs($receptionist, 'POST', [], [
            'id'   => (string) $patientId,
            'rxId' => (string) $rxId,
        ]);

        $this->expectException(HttpException::class);

        try {
            $this->controller->postDispense($request);
        } catch (HttpException $e) {
            self::assertSame(403, $e->statusCode);

            $stillPending = array_filter(
                $this->prescriptions->forPatient($patientId),
                static fn ($rx) => $rx->id === $rxId,
            );
            self::assertFalse(reset($stillPending)->isDispensed, 'the toggle was never flipped');

            throw $e;
        }
    }

    public function test_an_accountant_cannot_write_a_prescription(): void
    {
        $accountant  = $this->makeUser(UserRole::ACCOUNTANT);
        $patientId   = $this->makePatient();
        $encounterId = $this->makeEncounter($patientId);

        $request = $this->actingAs($accountant, 'POST', [
            'encounter_id'    => (string) $encounterId,
            'medication_name' => 'Should never be saved',
            'dosage'          => '1',
            'frequency'       => '1',
            'duration_days'   => '1',
            'icd_code'        => 'Z00',
        ], ['id' => (string) $patientId]);

        $this->expectException(HttpException::class);

        try {
            $this->controller->postPrescription($request);
        } catch (HttpException $e) {
            self::assertSame(403, $e->statusCode);
            self::assertSame([], $this->prescriptions->forEncounter($encounterId));

            throw $e;
        }
    }
}
