<?php

declare(strict_types=1);

namespace Aster\Tests\Feature\Presentation;

use Aster\Domain\Enum\ClinicalNoteType;
use Aster\Domain\Enum\DiagnosticCategory;
use Aster\Domain\Enum\UserRole;
use Aster\Infrastructure\Persistence\UserRepository;
use Aster\Presentation\Support\PatientDetailAccess;
use Aster\Tests\Support\DatabaseTestCase;

/**
 * The permission-mapping test the implementation prompt requires per real
 * role (§Testing Requirements): a user holding each of the six roles, via
 * the actual role_permissions/user_roles tables (never the enum directly),
 * gets exactly the tabs and actions the Patient Aggregate spec §4 assigns
 * to that role.
 */
final class PatientDetailAccessTest extends DatabaseTestCase
{
    private UserRepository $users;

    protected function setUp(): void
    {
        parent::setUp();

        $this->users = $this->container->get(UserRepository::class);
    }

    private function makeUser(UserRole $role): \Aster\Domain\Entity\User
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

    /** @return list<string> */
    private function tabKeys(\Aster\Domain\Entity\User $user): array
    {
        return array_column(PatientDetailAccess::visibleTabs($user), 'key');
    }

    // -----------------------------------------------------------------
    //  §4.1 Physician - the clinical cockpit
    // -----------------------------------------------------------------

    public function test_physician_sees_the_clinical_cockpit_tabs(): void
    {
        $user = $this->makeUser(UserRole::PHYSICIAN);

        self::assertSame(
            ['overview', 'encounters', 'notes', 'diagnostics', 'prescriptions', 'audit'],
            $this->tabKeys($user),
        );
        self::assertFalse(PatientDetailAccess::canViewTab($user, 'financial'), 'physician must not see the ledger');
    }

    public function test_physician_can_write_any_note_type_and_order_and_prescribe(): void
    {
        $user = $this->makeUser(UserRole::PHYSICIAN);

        self::assertSame(ClinicalNoteType::all(), PatientDetailAccess::allowedNoteTypes($user));
        self::assertTrue(PatientDetailAccess::canOrderDiagnostics($user));
        self::assertTrue(PatientDetailAccess::canWritePrescriptions($user));
        self::assertTrue(PatientDetailAccess::canDispense($user));
        self::assertNull(PatientDetailAccess::diagnosticCategoryScope($user), 'physician sees every category');
        self::assertTrue(PatientDetailAccess::auditScopedToOwnEntriesOnly($user), 'physician sees only its own audit entries');
    }

    // -----------------------------------------------------------------
    //  §4.2 Nurse - monitoring and vitals, not diagnosis or prescription
    // -----------------------------------------------------------------

    public function test_nurse_sees_monitoring_tabs_but_not_encounters_write_or_financial(): void
    {
        $user = $this->makeUser(UserRole::NURSE);

        self::assertSame(
            ['overview', 'encounters', 'notes', 'diagnostics', 'prescriptions'],
            $this->tabKeys($user),
        );
        self::assertFalse(PatientDetailAccess::canViewTab($user, 'audit'), 'nurse holds no audit permission at all');
        self::assertFalse(PatientDetailAccess::canViewTab($user, 'financial'));
    }

    public function test_nurse_may_only_write_vitals_and_triage_notes(): void
    {
        $user = $this->makeUser(UserRole::NURSE);

        self::assertSame(
            [ClinicalNoteType::VITALS, ClinicalNoteType::TRIAGE],
            PatientDetailAccess::allowedNoteTypes($user),
        );
        self::assertTrue(PatientDetailAccess::canWriteNoteType($user, ClinicalNoteType::VITALS));
        self::assertTrue(PatientDetailAccess::canWriteNoteType($user, ClinicalNoteType::TRIAGE));
        self::assertFalse(PatientDetailAccess::canWriteNoteType($user, ClinicalNoteType::PROGRESS_NOTE));
        self::assertFalse(PatientDetailAccess::canWriteNoteType($user, ClinicalNoteType::CHIEF_COMPLAINT));
        self::assertFalse(PatientDetailAccess::canWriteNoteType($user, ClinicalNoteType::DISCHARGE_SUMMARY));
    }

    public function test_nurse_cannot_order_diagnostics_or_write_prescriptions_but_can_dispense(): void
    {
        $user = $this->makeUser(UserRole::NURSE);

        self::assertFalse(PatientDetailAccess::canOrderDiagnostics($user));
        self::assertFalse(PatientDetailAccess::canWritePrescriptions($user));
        self::assertTrue(PatientDetailAccess::canDispense($user), 'spec §6 option (a): nurse is one of the two roles that may flip the dispensed toggle');
        self::assertFalse(PatientDetailAccess::canRecordResult($user, DiagnosticCategory::LAB), 'nurse holds diagnostics.view only, not any result-recording right');
    }

    // -----------------------------------------------------------------
    //  §4.3 Receptionist - registration and check-in, no clinical access
    // -----------------------------------------------------------------

    public function test_receptionist_sees_registration_tabs_only(): void
    {
        $user = $this->makeUser(UserRole::RECEPTIONIST);

        self::assertSame(['overview', 'encounters', 'audit'], $this->tabKeys($user));
        self::assertTrue(PatientDetailAccess::canEditDemographics($user));
        self::assertTrue(PatientDetailAccess::canProvisionPortalAccess($user));
        self::assertFalse(PatientDetailAccess::canSeeClinicalSummary($user), 'receptionist holds none of clinical_notes/diagnostics/prescriptions .view');
        self::assertTrue(PatientDetailAccess::auditScopedToOwnEntriesOnly($user));
    }

    // -----------------------------------------------------------------
    //  §4.4 Accountant - isolated financial ledger, zero clinical access
    // -----------------------------------------------------------------

    public function test_accountant_sees_only_overview_encounters_and_financial(): void
    {
        $user = $this->makeUser(UserRole::ACCOUNTANT);

        self::assertSame(['overview', 'encounters', 'financial'], $this->tabKeys($user));
        self::assertFalse(PatientDetailAccess::canSeeClinicalSummary($user), 'accountant must see identity only, no clinical fields (spec §4.4)');
        self::assertFalse(PatientDetailAccess::canEditDemographics($user));
        self::assertFalse(PatientDetailAccess::auditScopedToOwnEntriesOnly($user), 'accountant holds no audit permission of either kind');
        self::assertFalse(PatientDetailAccess::canViewTab($user, 'audit'));
    }

    // -----------------------------------------------------------------
    //  §4.5 Lab Technician - Lab category execution only
    // -----------------------------------------------------------------

    public function test_lab_technician_sees_only_overview_and_diagnostics(): void
    {
        $user = $this->makeUser(UserRole::LAB_TECHNICIAN);

        self::assertSame(['overview', 'diagnostics'], $this->tabKeys($user));
    }

    public function test_lab_technician_is_scoped_to_the_lab_category_only(): void
    {
        $user = $this->makeUser(UserRole::LAB_TECHNICIAN);

        self::assertSame(DiagnosticCategory::LAB, PatientDetailAccess::diagnosticCategoryScope($user));
        self::assertTrue(PatientDetailAccess::canRecordResult($user, DiagnosticCategory::LAB));
        self::assertFalse(PatientDetailAccess::canRecordResult($user, DiagnosticCategory::IMAGING));
        self::assertFalse(PatientDetailAccess::canRecordResult($user, DiagnosticCategory::PACS));
        self::assertFalse(PatientDetailAccess::canOrderDiagnostics($user), 'lab_technician executes orders, it does not place them');
        self::assertSame([], PatientDetailAccess::allowedNoteTypes($user));
        self::assertFalse(PatientDetailAccess::canWritePrescriptions($user));
        self::assertFalse(PatientDetailAccess::canDispense($user));
    }

    // -----------------------------------------------------------------
    //  §4.6 Super Admin - the superset, unscoped audit
    // -----------------------------------------------------------------

    public function test_super_admin_sees_every_tab_and_is_never_category_scoped(): void
    {
        $user = $this->makeUser(UserRole::SUPER_ADMIN);

        self::assertSame(
            ['overview', 'encounters', 'notes', 'diagnostics', 'prescriptions', 'financial', 'audit'],
            $this->tabKeys($user),
        );
        self::assertNull(PatientDetailAccess::diagnosticCategoryScope($user));
        self::assertFalse(PatientDetailAccess::auditScopedToOwnEntriesOnly($user), 'super_admin holds audit.view - the unscoped trail, not audit.view_own');
        self::assertSame(ClinicalNoteType::all(), PatientDetailAccess::allowedNoteTypes($user));
    }
}
