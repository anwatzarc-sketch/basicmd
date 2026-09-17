<?php

declare(strict_types=1);

namespace Aster\Infrastructure\Database;

use Aster\Infrastructure\Persistence\Database;
use Aster\Infrastructure\Persistence\UserRepository;
use Aster\Infrastructure\Security\PasswordHasher;
use RuntimeException;

/**
 * Pre-launch access reset: wipe every user, role, grant and role-permission
 * row, then rebuild a single `super_admin` role holding the ENTIRE
 * permission catalogue, plus one user who holds it.
 *
 * This is the state spec §4.1 describes as the starting point - one seeded
 * role, everything else created afterwards through Role Management - and it
 * is destructive by design. It exists to clear test/demo accounts before a
 * real launch, and must never be run against a deployment carrying real
 * staff accounts or real clinical/financial history.
 *
 * What it deliberately does NOT touch:
 *
 *   permissions   The fixed, seeded capability catalogue (spec §4, layer 1).
 *                 It describes what the SOFTWARE can gate and is not a
 *                 platform user's data - wiping it would break every future
 *                 role.
 *   patients,     Clinical/operational content unrelated to access control.
 *   doctors,      Doctor profiles survive with user_id nulled by the FK's
 *   appointments  own ON DELETE SET NULL - the profile is catalogue content,
 *                 the login was the account.
 *
 * LEDGER EXCEPTION - read this before using it anywhere real
 * ---------------------------------------------------------------------
 * consumption_ledger and receivable_payments are insert-only by contract
 * (corrected via a parent_entry_id contra row, never updated or deleted).
 * Their accountant_id FKs are ON DELETE RESTRICT precisely so a financial
 * row can never lose the identity of who posted it. To delete the users
 * that posted them, this class deletes those rows - knowingly breaking that
 * invariant. That is defensible ONLY for pre-launch test data. Against real
 * transactions it destroys financial history, and you should reassign the
 * rows instead of running this.
 */
final class PlatformReset
{
    public const string ROLE_SLUG  = 'super_admin';
    public const string ROLE_LABEL = 'Super Administrator';

    /** Tables cleared, in FK-safe order, before users/roles themselves. */
    private const array DEPENDENT_TABLES = [
        // Access-control graph, leaves first.
        'user_role_scopes',
        'user_roles',
        'role_permissions',
        // ON DELETE RESTRICT holders - see the LEDGER EXCEPTION note above.
        'clinical_notes',
        'diagnostic_orders',
        'e_prescriptions',
        'receivable_payments',
        'consumption_ledger',
    ];

    public function __construct(
        private readonly Database $db,
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
    ) {
    }

    /**
     * What run() would destroy, without destroying anything. Always call
     * this first and show it to whoever is about to pull the trigger.
     *
     * @return array<string, int>
     */
    public function preview(): array
    {
        $counts = [];

        foreach ([...self::DEPENDENT_TABLES, 'users', 'roles'] as $table) {
            $counts[$table] = $this->db->fetchInt('SELECT COUNT(*) FROM `' . $table . '`');
        }

        $counts['permissions (kept)'] = $this->db->fetchInt('SELECT COUNT(*) FROM `permissions`');

        return $counts;
    }

    /**
     * Wipe and rebuild. Runs inside one transaction - every statement here
     * is DML, so unlike a migration there is no implicit-commit hazard and
     * a failure rolls the whole thing back.
     *
     * @return array{user_id: int, role_id: int, permissions: int, deleted: array<string, int>}
     */
    public function run(string $fullName, string $email, #[\SensitiveParameter] string $plainPassword): array
    {
        $fullName = trim($fullName);
        $email    = mb_strtolower(trim($email));

        if ($fullName === '') {
            throw new RuntimeException('A full name is required for the new administrator.');
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('A valid email address is required for the new administrator.');
        }

        // Throws ValidationException with the specific failures - same rule
        // the real installer and the password-change screen apply.
        PasswordHasher::assertStrong($plainPassword);

        $passwordHash = $this->hasher->hash($plainPassword);

        return $this->db->transaction(function (Database $db) use ($fullName, $email, $passwordHash): array {
            $deleted = [];

            foreach (self::DEPENDENT_TABLES as $table) {
                $deleted[$table] = $db->execute('DELETE FROM `' . $table . '`');
            }

            // Nullable FKs (appointments.handled_by, audit_logs.user_id,
            // doctors.user_id, encounters.*, payments.verified_by,
            // system_settings.updated_by, ...) are ON DELETE SET NULL and
            // password_resets is ON DELETE CASCADE, so the database itself
            // handles those as these two run.
            $deleted['users'] = $db->execute('DELETE FROM `users`');
            $deleted['roles'] = $db->execute('DELETE FROM `roles`');

            $db->execute(
                'INSERT INTO `roles` (slug, label) VALUES (:slug, :label)',
                ['slug' => self::ROLE_SLUG, 'label' => self::ROLE_LABEL],
            );

            $roleId = $db->lastInsertId();

            // The whole catalogue, listed by join rather than hardcoded -
            // super_admin holds every permission "always, by construction"
            // (spec §4.2), including any a future migration adds.
            $granted = $db->execute(
                'INSERT INTO `role_permissions` (role_id, permission_id)
                 SELECT :role_id, p.id FROM `permissions` p',
                ['role_id' => $roleId],
            );

            // Goes through the repository, not raw SQL, so users.role and
            // the user_roles grant are written by the same code path every
            // other account creation uses.
            $userId = $this->users->create(
                fullName:     $fullName,
                email:        $email,
                passwordHash: $passwordHash,
                role:         self::ROLE_SLUG,
                status:       'active',
            );

            return [
                'user_id'     => $userId,
                'role_id'     => $roleId,
                'permissions' => $granted,
                'deleted'     => $deleted,
            ];
        });
    }
}
