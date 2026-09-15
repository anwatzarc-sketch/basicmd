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
            ],

            // Clinical staff: their own queue plus the articles they author.
            // No financial verification, no user administration.
            self::PHYSICIAN => [
                'dashboard.view',
                'appointments.view', 'appointments.write',
                'articles.view', 'articles.write',
                'patients.notes',
            ],

            // No screens exist for this role yet - Phase II stages 2+ grant
            // it real permissions (clinical_notes.*, etc.) once the clinical
            // record work lands. An empty set here is deliberate, not an
            // oversight: it matches the seeded (lack of) role_permissions
            // rows exactly, per migration 002_rbac.sql.
            self::NURSE => [],

            // Front desk: the full booking lifecycle and patient comms, but
            // explicitly NOT payment verification (separation of duties -
            // whoever books a slot must not also be able to mark it paid).
            self::RECEPTIONIST => [
                'dashboard.view',
                'appointments.view', 'appointments.write', 'appointments.view_all',
                'payments.view',
                'doctors.view',
                'services.view',
                'packages.view',
                'facilities.view',
                'inquiries.view', 'inquiries.write',
            ],

            // Finance: verifies proof-of-payment and reads revenue reporting.
            // Cannot alter clinical scheduling.
            self::ACCOUNTANT => [
                'dashboard.view', 'dashboard.finance',
                'appointments.view', 'appointments.view_all',
                'payments.view', 'payments.verify', 'payments.refund',
                'payment_methods.view', 'payment_methods.write',
                'packages.view',
                'reports.view',
            ],

            // Same deliberate empty set as NURSE - see that case's comment.
            self::LAB_TECHNICIAN => [],
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
