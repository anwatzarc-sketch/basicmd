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
 */
enum UserRole: string
{
    case SUPER_ADMIN  = 'super_admin';
    case DOCTOR       = 'doctor';
    case RECEPTIONIST = 'receptionist';
    case FINANCE      = 'finance';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN  => 'Super Administrator',
            self::DOCTOR       => 'Doctor',
            self::RECEPTIONIST => 'Receptionist',
            self::FINANCE      => 'Finance Officer',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::SUPER_ADMIN  => 'bg-violet-100 text-violet-800 border-violet-200',
            self::DOCTOR       => 'bg-medical-100 text-medical-800 border-medical-200',
            self::RECEPTIONIST => 'bg-sky-100 text-sky-800 border-sky-200',
            self::FINANCE      => 'bg-amber-100 text-amber-800 border-amber-200',
        };
    }

    /**
     * Every permission this role holds.
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
            self::DOCTOR => [
                'dashboard.view',
                'appointments.view', 'appointments.write',
                'articles.view', 'articles.write',
                'patients.notes',
            ],

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
            self::FINANCE => [
                'dashboard.view', 'dashboard.finance',
                'appointments.view', 'appointments.view_all',
                'payments.view', 'payments.verify', 'payments.refund',
                'payment_methods.view', 'payment_methods.write',
                'packages.view',
                'reports.view',
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
            self::SUPER_ADMIN, self::RECEPTIONIST, self::DOCTOR => '/dashboard',
            self::FINANCE => '/payments',
        };
    }

    /** Only this role is scoped down to a single doctor's own queue. */
    public function isClinical(): bool
    {
        return $this === self::DOCTOR;
    }

    /** @return list<self> */
    public static function all(): array
    {
        return [self::SUPER_ADMIN, self::DOCTOR, self::RECEPTIONIST, self::FINANCE];
    }
}
