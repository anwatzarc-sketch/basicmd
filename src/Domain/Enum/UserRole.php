<?php

declare(strict_types=1);

namespace Aster\Domain\Enum;

/**
 * Staff roles and the permission matrix behind RBAC.
 *
 * Permissions are coarse verbs on a resource ("appointments.write") rather
 * than per-row rules. Row-level scoping - a doctor seeing only their own
 * queue - is applied in the repository layer, not here, because it needs a
 * doctor_id this enum has no access to.
 *
 * The permissions() matrix here is a FALLBACK, not the source of truth: the
 * database now holds the real assignment (roles / permissions /
 * role_permissions / user_roles, migration 002_rbac.sql), and
 * User::resolvePermissions() reads that first. This matrix exists so a User
 * built from a partial row - or any environment where the relational tables
 * are momentarily empty - still has a correct, known permission set rather
 * than none at all. Keep the two in agreement: this is the historical
 * record migration 002 was seeded from, not a second place to grant access.
 *
 * Role slugs (PHYSICIAN='physician', ACCOUNTANT='accountant') were renamed
 * from the original 'doctor'/'finance' as part of that same migration, to
 * match the vocabulary Phase II's clinical and financial modules use
 * throughout. NURSE and LAB_TECHNICIAN are new; both start with an empty
 * permission set until their own screens exist in a later stage, rather
 * than guessing at one now.
 */
enum UserRole: string
{
    case SUPER_ADMIN    = 'super_admin';
    case PHYSICIAN      = 'physician';
    case NURSE          = 'nurse';
    case RECEPTIONIST   = 'receptionist';
    case ACCOUNTANT     = 'accountant';
    case LAB_TECHNICIAN = 'lab_technician';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN    => 'Super Administrator',
            self::PHYSICIAN      => 'Physician',
            self::NURSE          => 'Nurse',
            self::RECEPTIONIST   => 'Receptionist',
            self::ACCOUNTANT     => 'Accountant',
            self::LAB_TECHNICIAN => 'Lab Technician',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::SUPER_ADMIN    => 'bg-violet-100 text-violet-800 border-violet-200',
            self::PHYSICIAN      => 'bg-medical-100 text-medical-800 border-medical-200',
            self::NURSE          => 'bg-teal-100 text-teal-800 border-teal-200',
            self::RECEPTIONIST   => 'bg-sky-100 text-sky-800 border-sky-200',
            self::ACCOUNTANT     => 'bg-amber-100 text-amber-800 border-amber-200',
            self::LAB_TECHNICIAN => 'bg-zinc-100 text-zinc-800 border-zinc-200',
        };
    }

    /**
     * Every permission this role holds, absent a relational override.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        return match ($this) {
            // Unrestricted. Listed explicitly rather than special-cased, so
            // granting a new permission stays a deliberate decision.
            self::SUPER_ADMIN => [
                'dashboard.view', 'dashboard.finance',
                'appointments.view', 'appointments.write', 'appointments.delete', 'appointments.view_all',
                'payments.view', 'payments.verify', 'payments.refund',
                'doctors.view', 'doctors.write',
                'services.view', 'services.write',
                'packages.view', 'packages.write',
                'facilities.view', 'facilities.write',
                'articles.view', 'articles.write', 'articles.publish',
                'inquiries.view', 'inquiries.write',
                'users.view', 'users.write',
                'settings.view', 'settings.write',
                'payment_methods.view', 'payment_methods.write',
                'audit.view',
                'reports.view',
                'patients.view', 'patients.write', 'patients.notes',
                'encounters.view', 'encounters.write',
                'billing.view', 'billing.write', 'billing.discharge', 'billing.override',
                'clinical_notes.view', 'clinical_notes.write', 'clinical_notes.write_vitals',
                'diagnostics.view', 'diagnostics.order', 'diagnostics.result', 'diagnostics.result_lab',
                'prescriptions.view', 'prescriptions.write', 'prescriptions.dispense',
                'brand.manage',
                'lab_catalog.manage',
            ],

            // Clinical staff: their own queue plus the articles they author.
            // No financial verification, no user administration. Sees the
            // MPI and the encounter workbench read-write, per migration
            // 006 - upgradeOpdToIpd() names a physician directly. Documents,
            // orders and prescribes on the Patient Detail page (migration
            // 007), and sees only its OWN audit entries there
            // (audit.view_own), not the whole system trail.
            self::PHYSICIAN => [
                'dashboard.view',
                'appointments.view', 'appointments.write',
                'articles.view', 'articles.write',
                'patients.notes',
                'patients.view',
                'encounters.view', 'encounters.write',
                'clinical_notes.view', 'clinical_notes.write',
                'diagnostics.view', 'diagnostics.order',
                'prescriptions.view', 'prescriptions.write', 'prescriptions.dispense',
                'audit.view_own',
            ],

            // First real permissions this role has ever held (migration 002
            // deliberately left it empty pending the clinical documentation
            // screens; migration 007 is that work). Monitoring and vitals
            // capture, not diagnosis or prescription: clinical_notes.write_vitals
            // rather than clinical_notes.write restricts note creation to
            // note_type IN ('vitals','triage') at the point it is
            // authorised - see PatientDetailAccess. Reads diagnostics and
            // prescriptions for ward safety context but creates neither.
            // prescriptions.dispense is the bare dispensed toggle (spec's
            // own gap: e_prescriptions has no dispensed_by/at columns and
            // no pharmacist role exists) - nurse and physician are the
            // closest real fit until one does.
            self::NURSE => [
                'dashboard.view',
                'patients.view',
                'encounters.view',
                'clinical_notes.view', 'clinical_notes.write_vitals',
                'diagnostics.view',
                'prescriptions.view', 'prescriptions.dispense',
            ],

            // Front desk: the full booking lifecycle and patient comms, but
            // explicitly NOT payment verification (separation of duties -
            // whoever books a slot must not also be able to mark it paid).
            // audit.view_own answers "who edited this patient's demographics"
            // without exposing the system-wide trail.
            self::RECEPTIONIST => [
                'dashboard.view',
                'appointments.view', 'appointments.write', 'appointments.view_all',
                'payments.view',
                'doctors.view',
                'services.view',
                'packages.view',
                'facilities.view',
                'inquiries.view', 'inquiries.write',
                'patients.view', 'patients.write',
                'encounters.view', 'encounters.write',
                'audit.view_own',
            ],

            // Finance: verifies proof-of-payment and reads revenue reporting.
            // Cannot alter clinical scheduling. Owns the consumption ledger
            // and payment posting (BillingService's own accountant_id
            // columns), and the financial clearance gate - discharge and
            // override are FIN-003/004's whole point. Deliberately holds
            // none of the clinical_notes/diagnostics/prescriptions
            // permissions - that separation from clinical content is the
            // point of this role existing apart from PHYSICIAN.
            self::ACCOUNTANT => [
                'dashboard.view', 'dashboard.finance',
                'appointments.view', 'appointments.view_all',
                'payments.view', 'payments.verify', 'payments.refund',
                'payment_methods.view', 'payment_methods.write',
                'packages.view',
                'reports.view',
                'patients.view',
                'encounters.view',
                'billing.view', 'billing.write', 'billing.discharge', 'billing.override',
            ],

            // First real permissions this role has ever held (see NURSE's
            // comment - same migration 002 -> 007 history). Scoped to
            // executing Lab orders only: diagnostics.result_lab rather than
            // diagnostics.result, because "lab_technician" is literally
            // this role's name and nothing in this enum represents an
            // Imaging/PACS operator - those categories stay with the
            // ordering physician and super_admin until a Radiology role
            // exists. patients.view is safety context (identity, allergies)
            // for running a test, not general MPI access.
            self::LAB_TECHNICIAN => [
                'dashboard.view',
                'patients.view',
                'diagnostics.view', 'diagnostics.result_lab',
            ],
        };
    }

    public function can(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    /** Landing page after login, so each role starts where its work is. */
    public function homeRoute(): string
    {
        return match ($this) {
            self::SUPER_ADMIN, self::RECEPTIONIST, self::PHYSICIAN, self::NURSE, self::LAB_TECHNICIAN => '/dashboard',
            self::ACCOUNTANT => '/payments',
        };
    }

    /** Only this role is scoped down to a single doctor's own queue. */
    public function isClinical(): bool
    {
        return $this === self::PHYSICIAN;
    }

    /** @return list<self> */
    public static function all(): array
    {
        return [
            self::SUPER_ADMIN,
            self::PHYSICIAN,
            self::NURSE,
            self::RECEPTIONIST,
            self::ACCOUNTANT,
            self::LAB_TECHNICIAN,
        ];
    }
}
