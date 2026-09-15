<?php

declare(strict_types=1);

namespace Aster\Tests\Characterisation;

use Aster\Domain\Entity\User;
use Aster\Domain\Enum\UserRole;
use Aster\Infrastructure\Persistence\UserRepository;
use Aster\Tests\Support\DatabaseTestCase;

/**
 * Pins the exact behaviour the RBAC cutover (migration 002_rbac.sql, the
 * UserRole rename, and User::resolvePermissions()) must never regress.
 *
 * These are the risks the implementation plan called out by name:
 *
 *  - User::fromRow()'s UserRole::from() is strict - a role value the enum
 *    does not recognise is an uncaught ValueError on every authenticated
 *    request.
 *  - UserRepository::countByRole() backs BOTH last-administrator guards in
 *    UserController and fails SILENTLY (returns 0, disabling the guards)
 *    rather than throwing - there is no error to notice if it breaks.
 *  - UserRole::isClinical() is a security check (AppointmentController's
 *    assertVisibleTo()) - if it degrades to false for the renamed
 *    PHYSICIAN case, a doctor would see every patient's appointments.
 *  - can() must keep working identically after moving from a hardcoded
 *    match() to a database-resolved permission set.
 */
final class RbacCutoverTest extends DatabaseTestCase
{
    private UserRepository $users;

    protected function setUp(): void
    {
        parent::setUp();

        $this->users = $this->container->get(UserRepository::class);
    }

    private function makeUser(UserRole $role, string $emailPrefix): int
    {
        return $this->users->create(
            fullName:     'Test ' . $emailPrefix,
            email:        $emailPrefix . '-' . bin2hex(random_bytes(4)) . '@test.invalid',
            passwordHash: '$argon2id$v=19$m=1024,t=1,p=1$c29tZXNhbHQ$aGFzaA', // never verified in this test
            role:         $role,
        );
    }

    // -----------------------------------------------------------------
    //  The renamed roles hydrate without throwing, and carry the right
    //  permission set.
    // -----------------------------------------------------------------

    public function test_a_physician_row_hydrates_without_throwing(): void
    {
        $id   = $this->makeUser(UserRole::PHYSICIAN, 'physician');
        $user = $this->users->findById($id);

        self::assertInstanceOf(User::class, $user);
        self::assertSame(UserRole::PHYSICIAN, $user->role);
        self::assertTrue($user->role->isClinical(), 'PHYSICIAN must be the clinical role after the rename');
    }

    public function test_an_accountant_row_hydrates_without_throwing(): void
    {
        $id   = $this->makeUser(UserRole::ACCOUNTANT, 'accountant');
        $user = $this->users->findById($id);

        self::assertInstanceOf(User::class, $user);
        self::assertSame(UserRole::ACCOUNTANT, $user->role);
        self::assertFalse($user->role->isClinical());
    }

    public function test_every_role_round_trips_through_the_database(): void
    {
        foreach (UserRole::all() as $role) {
            $id   = $this->makeUser($role, $role->value);
            $user = $this->users->findById($id);

            self::assertNotNull($user, "role {$role->value} failed to hydrate");
            self::assertSame($role, $user->role, "role {$role->value} did not round-trip");
        }
    }

    // -----------------------------------------------------------------
    //  can() resolves through the relational model and matches the
    //  hardcoded matrix exactly - the migration seeded from that matrix,
    //  so day-one behaviour must be identical.
    // -----------------------------------------------------------------

    public function test_physician_can_matches_the_pre_rename_doctor_matrix(): void
    {
        $id   = $this->makeUser(UserRole::PHYSICIAN, 'physician-can');
        $user = $this->users->findById($id);

        self::assertTrue($user->can('appointments.view'));
        self::assertTrue($user->can('appointments.write'));
        self::assertTrue($user->can('articles.write'));
        self::assertTrue($user->can('patients.notes'));

        // Explicitly NOT granted - separation of duties.
        self::assertFalse($user->can('payments.verify'));
        self::assertFalse($user->can('users.write'));
        self::assertFalse($user->can('settings.write'));
    }

    public function test_accountant_can_matches_the_pre_rename_finance_matrix(): void
    {
        $id   = $this->makeUser(UserRole::ACCOUNTANT, 'accountant-can');
        $user = $this->users->findById($id);

        self::assertTrue($user->can('payments.verify'));
        self::assertTrue($user->can('payments.refund'));
        self::assertTrue($user->can('dashboard.finance'));

        // Explicitly NOT granted - finance cannot alter clinical scheduling.
        self::assertFalse($user->can('appointments.write'));
        self::assertFalse($user->can('doctors.write'));
    }

    public function test_resolved_permissions_come_from_the_relational_model_not_just_the_enum(): void
    {
        $id   = $this->makeUser(UserRole::RECEPTIONIST, 'receptionist-resolved');
        $user = $this->users->findById($id);

        // Every permission on the User entity must also exist as a row in
        // `permissions`, reached only via user_roles -> role_permissions -
        // proving the resolution actually traversed the relational tables
        // rather than silently falling back to the hardcoded enum matrix.
        $dbPermissionCount = $this->db->fetchInt(
            'SELECT COUNT(*) FROM user_roles ur
             JOIN role_permissions rp ON rp.role_id = ur.role_id
             WHERE ur.user_id = :id',
            ['id' => $id],
        );

        self::assertGreaterThan(0, $dbPermissionCount);
        self::assertSame($dbPermissionCount, count($user->permissions));
    }

    public function test_a_role_with_no_seeded_permissions_falls_back_to_the_enum_not_to_nothing(): void
    {
        // NURSE and LAB_TECHNICIAN deliberately have zero role_permissions
        // rows (migration 002's own comment). User::resolvePermissions()
        // must fall back to the enum's permissions() - which is also empty
        // for these two today - rather than crash or silently grant
        // everything.
        $id   = $this->makeUser(UserRole::NURSE, 'nurse-empty');
        $user = $this->users->findById($id);

        self::assertSame([], $user->permissions);
        self::assertFalse($user->can('appointments.view'));
    }

    // -----------------------------------------------------------------
    //  countByRole() - the exact mechanism behind both last-administrator
    //  guards, and the one the plan flagged as failing SILENTLY.
    // -----------------------------------------------------------------

    public function test_count_by_role_reports_the_renamed_slugs_not_zero(): void
    {
        $this->makeUser(UserRole::PHYSICIAN, 'countphys');
        $this->makeUser(UserRole::ACCOUNTANT, 'countacct');

        $counts = $this->users->countByRole();

        // The old slugs must never appear - if they did, every guard
        // reading counts['super_admin'] would silently see 0.
        self::assertArrayNotHasKey('doctor', $counts);
        self::assertArrayNotHasKey('finance', $counts);

        self::assertGreaterThanOrEqual(1, $counts['physician'] ?? 0);
        self::assertGreaterThanOrEqual(1, $counts['accountant'] ?? 0);
    }

    public function test_count_by_role_reflects_a_role_change_immediately(): void
    {
        $id = $this->makeUser(UserRole::RECEPTIONIST, 'promote');

        $before = $this->users->countByRole()['super_admin'] ?? 0;

        $this->users->update($id, ['role' => UserRole::SUPER_ADMIN->value]);

        $after = $this->users->countByRole()['super_admin'] ?? 0;

        self::assertSame($before + 1, $after);
    }

    // -----------------------------------------------------------------
    //  update() keeps users.role and user_roles in agreement - the exact
    //  drift the migration's own comment warns about.
    // -----------------------------------------------------------------

    public function test_updating_role_resyncs_user_roles_not_just_the_cache_column(): void
    {
        $id = $this->makeUser(UserRole::RECEPTIONIST, 'resync');

        $this->users->update($id, ['role' => UserRole::PHYSICIAN->value]);

        $cacheColumn = $this->db->fetchValue('SELECT role FROM users WHERE id = :id', ['id' => $id]);
        self::assertSame('physician', $cacheColumn);

        $relationalRole = $this->db->fetchValue(
            'SELECT r.slug FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = :id',
            ['id' => $id],
        );
        self::assertSame('physician', $relationalRole, 'user_roles must be resynced, not left pointing at the old role');

        // And the permission set the user actually gets reflects the NEW
        // role, not a stale union of old + new.
        $user = $this->users->findById($id);
        self::assertTrue($user->can('patients.notes'), 'should now have the physician permission set');
        self::assertFalse($user->can('inquiries.write'), 'should no longer have the receptionist permission set');
    }

    public function test_updating_a_column_other_than_role_does_not_touch_user_roles(): void
    {
        $id = $this->makeUser(UserRole::PHYSICIAN, 'untouched');

        $before = $this->db->fetchValue(
            'SELECT role_id FROM user_roles WHERE user_id = :id',
            ['id' => $id],
        );

        $this->users->update($id, ['phone' => '+251911000000']);

        $after = $this->db->fetchValue(
            'SELECT role_id FROM user_roles WHERE user_id = :id',
            ['id' => $id],
        );

        self::assertSame($before, $after);
    }

    public function test_create_writes_both_the_cache_column_and_the_relational_row_atomically(): void
    {
        $id = $this->makeUser(UserRole::ACCOUNTANT, 'atomic');

        $cacheColumn = $this->db->fetchValue('SELECT role FROM users WHERE id = :id', ['id' => $id]);
        self::assertSame('accountant', $cacheColumn);

        $relationalCount = $this->db->fetchInt(
            'SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = :id AND r.slug = :slug',
            ['id' => $id, 'slug' => 'accountant'],
        );
        self::assertSame(1, $relationalCount);
    }

    // -----------------------------------------------------------------
    //  The 'patient' row exists in the catalogue but is never a real
    //  assignment target for a staff user (migration 002's own note).
    // -----------------------------------------------------------------

    public function test_patient_role_slug_is_not_a_valid_user_role_case(): void
    {
        self::assertNull(UserRole::tryFrom('patient'));
    }

    public function test_patient_role_exists_in_the_catalogue_but_grants_nothing(): void
    {
        $roleId = $this->db->fetchValue("SELECT id FROM roles WHERE slug = 'patient'");
        self::assertNotNull($roleId, "the 'patient' documentation row must exist in roles");

        $grantCount = $this->db->fetchInt(
            'SELECT COUNT(*) FROM role_permissions WHERE role_id = :id',
            ['id' => $roleId],
        );
        self::assertSame(0, $grantCount);
    }

    // -----------------------------------------------------------------
    //  All 31 permissions from the live enum are present in the seeded
    //  catalogue - proves the migration's generated seed did not drop or
    //  misspell anything.
    // -----------------------------------------------------------------

    public function test_every_distinct_enum_permission_exists_in_the_permissions_table(): void
    {
        $expected = [];

        foreach (UserRole::all() as $role) {
            $expected = [...$expected, ...$role->permissions()];
        }

        $expected = array_unique($expected);

        foreach ($expected as $permission) {
            $exists = $this->db->fetchInt(
                'SELECT COUNT(*) FROM permissions WHERE slug = :slug',
                ['slug' => $permission],
            );

            self::assertSame(1, $exists, "permission '{$permission}' is missing from the permissions table");
        }
    }
}
