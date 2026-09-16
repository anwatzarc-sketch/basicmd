-- =====================================================================
--  008 - Role Management & Ward-Scoped Access
-- =====================================================================
--  Spec: aster_patient_aggregate_spec.md §4.2/§4.3/§4.5.
--
--  IMPORTANT - this migration does NOT do what an earlier draft of the
--  implementation prompt assumed was already true. Phase 1 of that work
--  queried the live database (all of 001-007 applied) and found:
--
--    - 7 roles already exist (super_admin + physician/nurse/receptionist/
--      accountant/lab_technician/patient), each with its own real,
--      deliberately-designed role_permissions set (migrations 002/006/007).
--    - Users are NOT collapsed onto super_admin - they still hold their
--      original roles.
--    - users.role is a NOT NULL ENUM, not a nullable varchar.
--    - user_role_scopes does not exist.
--
--  That is the opposite of the "only super_admin exists, everyone is
--  over-privileged onto it" starting state the prompt described. Rather
--  than destroy the existing, working role catalogue to manufacture a
--  "before" state nobody asked for, this migration builds Role Management
--  and ward scoping ON TOP of the real current state:
--
--    - The 6 existing roles and their role_permissions are left untouched.
--    - No user is reassigned to super_admin.
--    - users.role is widened (ENUM -> VARCHAR) so it can go on being a
--      synced display cache once Role Management lets a platform user
--      create a role name this ENUM has never heard of - never so it can
--      be read for an authorization decision (spec §4.3 forbids that
--      regardless of the column's type).
--
--  See the implementation report for the full discrepancy writeup.
-- =====================================================================

-- ---------------------------------------------------------------------
--  1. roles.archived_at - Role Management archives rather than
--     hard-deletes (spec §4.2), so a role that still has user_roles rows
--     is never actually removed, only hidden from new assignment.
-- ---------------------------------------------------------------------

ALTER TABLE `roles`
  ADD COLUMN `archived_at` DATETIME NULL AFTER `label`;

-- ---------------------------------------------------------------------
--  2. New permissions: roles.manage / wards.manage (spec §4.2). Both are
--     super_admin-only, no delegation path, per spec.
-- ---------------------------------------------------------------------

INSERT INTO `permissions` (`slug`) VALUES
  ('roles.manage'),
  ('wards.manage');

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
WHERE r.slug = 'super_admin' AND p.slug IN ('roles.manage', 'wards.manage');

-- ---------------------------------------------------------------------
--  3. user_role_scopes (spec §4.5) - ward scope lives on the GRANT, not
--     the role definition, so one role stays reusable across many wards.
--     Composite PK mirrors the spec's own table shape exactly; FK to
--     user_roles(user_id, role_id) so a scope row cannot outlive its
--     grant.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `user_role_scopes` (
  `user_id`   BIGINT UNSIGNED NOT NULL,
  `role_id`   INT UNSIGNED    NOT NULL,
  `ward_name` VARCHAR(64)     NOT NULL,
  PRIMARY KEY (`user_id`, `role_id`, `ward_name`),
  CONSTRAINT `fk_user_role_scopes_grant` FOREIGN KEY (`user_id`, `role_id`)
    REFERENCES `user_roles` (`user_id`, `role_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  4. users.role: ENUM -> nullable VARCHAR (spec §4.3). Widening a
--     string-family column preserves every existing value verbatim -
--     no UPDATE needed, unlike migration 002's three-step ENUM rename.
--     Still NOT read by any authorization check anywhere in the
--     application; this only lets it go on being a synced display label
--     once a role Role Management creates has a name this column's old
--     fixed ENUM could never have held.
-- ---------------------------------------------------------------------

ALTER TABLE `users`
  MODIFY COLUMN `role` VARCHAR(64) NULL DEFAULT NULL;
