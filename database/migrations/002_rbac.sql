-- =====================================================================
--  002 - Dynamic RBAC
-- =====================================================================
--  Replaces the static `users.role` ENUM with a relational model: roles,
--  permissions, role_permissions, user_roles. Seeded from the EXACT
--  permission matrix UserRole::permissions() holds today, so behaviour is
--  identical the moment this migration finishes - this is a data
--  migration, not a redesign.
--
--  `users.role` is RETAINED, not dropped. It still backs:
--    - User::fromRow()'s strict UserRole::from() (a missing/unmapped value
--      there is an uncaught ValueError on every authenticated request)
--    - the ix_users_role_status index
--    - UserRepository::all()'s ORDER BY u.role
--    - UserRepository::countByRole(), which backs BOTH last-administrator
--      guards in UserController (self-lockout and last-admin-standing) -
--      this one fails SILENTLY (returns 0, disabling the guards) rather
--      than throwing, which is exactly the kind of regression a removed
--      column would cause with no error to notice.
--  It becomes a synced cache of the user's single primary role rather than
--  the source of truth; UserRepository::create()/update() keep it and
--  user_roles in agreement (see UserRepository changes in this stage).
--
--  Role rename (decision 5, confirmed): doctor -> physician,
--  finance -> accountant. Two new roles added: nurse, lab_technician.
--  Renaming an ENUM value safely (without STRICT_ALL_TABLES rejecting a
--  row whose current value is about to disappear) takes three steps:
--  widen the ENUM to a superset, rewrite the data, then narrow it to the
--  final set. All three happen below, in order.
-- =====================================================================

-- ---------------------------------------------------------------------
--  1. Relational RBAC tables
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `roles` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`       VARCHAR(40)  NOT NULL,
  `label`      VARCHAR(80)  NOT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`       VARCHAR(60)  NOT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id`       INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  KEY `ix_role_permissions_permission` (`permission_id`),
  CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per (user, role). A user has exactly one row in current
-- practice - UserController assigns a single primary role - but the shape
-- supports more than one without a further migration, which is the whole
-- point of moving off a single-valued ENUM.
CREATE TABLE IF NOT EXISTS `user_roles` (
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `role_id`    INT UNSIGNED    NOT NULL,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `role_id`),
  KEY `ix_user_roles_role` (`role_id`),
  CONSTRAINT `fk_user_roles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_user_roles_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  2. Seed permissions (31 rows - the distinct set UserRole::permissions()
--     emits today, generated from the live enum, not hand-typed)
-- ---------------------------------------------------------------------

INSERT INTO `permissions` (`slug`) VALUES
  ('appointments.delete'),
  ('appointments.view'),
  ('appointments.view_all'),
  ('appointments.write'),
  ('articles.publish'),
  ('articles.view'),
  ('articles.write'),
  ('audit.view'),
  ('dashboard.finance'),
  ('dashboard.view'),
  ('doctors.view'),
  ('doctors.write'),
  ('facilities.view'),
  ('facilities.write'),
  ('inquiries.view'),
  ('inquiries.write'),
  ('packages.view'),
  ('packages.write'),
  ('patients.notes'),
  ('payment_methods.view'),
  ('payment_methods.write'),
  ('payments.refund'),
  ('payments.verify'),
  ('payments.view'),
  ('reports.view'),
  ('services.view'),
  ('services.write'),
  ('settings.view'),
  ('settings.write'),
  ('users.view'),
  ('users.write');

-- ---------------------------------------------------------------------
--  3. Seed roles: 6 assignable staff roles + 1 documentation-only
--     'patient' row.
--
--  'patient' is listed because FRS section 4.2 names it among the
--  "required roles" for the relational model, but patients are never
--  `users` rows (database/schema.sql:35-36: "Patients are NOT accounts").
--  The row exists so the role catalogue is a complete reference; nothing
--  in application code ever inserts a user_roles row pointing at it, and
--  no role_permissions row is seeded for it below. Patient portal
--  authorization is handled by its own session namespace (Stage 4), not
--  by this table.
-- ---------------------------------------------------------------------

INSERT INTO `roles` (`slug`, `label`) VALUES
  ('super_admin', 'Super Administrator'),
  ('physician', 'Physician'),
  ('nurse', 'Nurse'),
  ('receptionist', 'Receptionist'),
  ('accountant', 'Accountant'),
  ('lab_technician', 'Lab Technician'),
  ('patient', 'Patient');

-- ---------------------------------------------------------------------
--  4. Seed role_permissions from UserRole::permissions(), role slugs
--     already renamed (doctor -> physician, finance -> accountant).
--     nurse and lab_technician intentionally get NO rows here - Phase II
--     grants them permissions in a later migration once their screens
--     exist, rather than guessing a permission set now.
-- ---------------------------------------------------------------------

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
WHERE (r.slug, p.slug) IN (
  ('super_admin', 'dashboard.view'),
  ('super_admin', 'dashboard.finance'),
  ('super_admin', 'appointments.view'),
  ('super_admin', 'appointments.write'),
  ('super_admin', 'appointments.delete'),
  ('super_admin', 'appointments.view_all'),
  ('super_admin', 'payments.view'),
  ('super_admin', 'payments.verify'),
  ('super_admin', 'payments.refund'),
  ('super_admin', 'doctors.view'),
  ('super_admin', 'doctors.write'),
  ('super_admin', 'services.view'),
  ('super_admin', 'services.write'),
  ('super_admin', 'packages.view'),
  ('super_admin', 'packages.write'),
  ('super_admin', 'facilities.view'),
  ('super_admin', 'facilities.write'),
  ('super_admin', 'articles.view'),
  ('super_admin', 'articles.write'),
  ('super_admin', 'articles.publish'),
  ('super_admin', 'inquiries.view'),
  ('super_admin', 'inquiries.write'),
  ('super_admin', 'users.view'),
  ('super_admin', 'users.write'),
  ('super_admin', 'settings.view'),
  ('super_admin', 'settings.write'),
  ('super_admin', 'payment_methods.view'),
  ('super_admin', 'payment_methods.write'),
  ('super_admin', 'audit.view'),
  ('super_admin', 'reports.view'),
  ('physician', 'dashboard.view'),
  ('physician', 'appointments.view'),
  ('physician', 'appointments.write'),
  ('physician', 'articles.view'),
  ('physician', 'articles.write'),
  ('physician', 'patients.notes'),
  ('receptionist', 'dashboard.view'),
  ('receptionist', 'appointments.view'),
  ('receptionist', 'appointments.write'),
  ('receptionist', 'appointments.view_all'),
  ('receptionist', 'payments.view'),
  ('receptionist', 'doctors.view'),
  ('receptionist', 'services.view'),
  ('receptionist', 'packages.view'),
  ('receptionist', 'facilities.view'),
  ('receptionist', 'inquiries.view'),
  ('receptionist', 'inquiries.write'),
  ('accountant', 'dashboard.view'),
  ('accountant', 'dashboard.finance'),
  ('accountant', 'appointments.view'),
  ('accountant', 'appointments.view_all'),
  ('accountant', 'payments.view'),
  ('accountant', 'payments.verify'),
  ('accountant', 'payments.refund'),
  ('accountant', 'payment_methods.view'),
  ('accountant', 'payment_methods.write'),
  ('accountant', 'packages.view'),
  ('accountant', 'reports.view')
);

-- ---------------------------------------------------------------------
--  5. Rename users.role ENUM values: doctor -> physician,
--     finance -> accountant. Three-step widen/rewrite/narrow so
--     STRICT_ALL_TABLES never rejects an existing row.
-- ---------------------------------------------------------------------

ALTER TABLE `users`
  MODIFY COLUMN `role` ENUM('super_admin','doctor','physician','nurse','receptionist','accountant','finance','lab_technician')
  NOT NULL DEFAULT 'receptionist';

UPDATE `users` SET `role` = 'physician' WHERE `role` = 'doctor';
UPDATE `users` SET `role` = 'accountant' WHERE `role` = 'finance';

ALTER TABLE `users`
  MODIFY COLUMN `role` ENUM('super_admin','physician','nurse','receptionist','accountant','lab_technician')
  NOT NULL DEFAULT 'receptionist';

-- ---------------------------------------------------------------------
--  6. Backfill user_roles from users.role (now renamed), for every
--     existing staff account.
-- ---------------------------------------------------------------------

INSERT INTO `user_roles` (`user_id`, `role_id`)
SELECT u.id, r.id
FROM `users` u
JOIN `roles` r ON r.slug = u.role
WHERE u.deleted_at IS NULL
ON DUPLICATE KEY UPDATE `user_id` = `user_id`;
