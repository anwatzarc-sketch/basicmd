-- =====================================================================
--  006 - Phase II permissions
-- =====================================================================
--  Seeds the permission strings Stage 4's admin screens gate on, and
--  grants them to the existing roles whose job the corresponding screen
--  actually is. nurse and lab_technician still get nothing here,
--  deliberately, matching migration 002's own comment: their own
--  screens (clinical documentation, diagnostic result entry) are not
--  part of this pass, and guessing a permission set for a screen that
--  does not exist yet is exactly what that comment already ruled out.
-- =====================================================================

INSERT INTO `permissions` (`slug`) VALUES
  ('patients.view'),
  ('patients.write'),
  ('encounters.view'),
  ('encounters.write'),
  ('billing.view'),
  ('billing.write'),
  ('billing.discharge'),
  ('billing.override');

-- super_admin: everything, per its own existing "listed explicitly rather
-- than special-cased" convention (UserRole::permissions()).
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
WHERE r.slug = 'super_admin'
  AND p.slug IN (
    'patients.view', 'patients.write',
    'encounters.view', 'encounters.write',
    'billing.view', 'billing.write', 'billing.discharge', 'billing.override'
  );

-- receptionist: the check-in bridge and MPI lookup are front-desk work.
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
WHERE r.slug = 'receptionist'
  AND p.slug IN ('patients.view', 'patients.write', 'encounters.view', 'encounters.write');

-- physician: reads the MPI and the encounter workbench, and is the actor
-- upgradeOpdToIpd() names directly - but does not post charges or
-- discharge on financial grounds.
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
WHERE r.slug = 'physician'
  AND p.slug IN ('patients.view', 'encounters.view', 'encounters.write');

-- accountant: the ledger and payment posting are literally this role's
-- job (BillingService's own accountant_id columns); discharge and
-- override are the financial clearance gate FIN-003/004 exists for.
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
WHERE r.slug = 'accountant'
  AND p.slug IN (
    'patients.view', 'encounters.view',
    'billing.view', 'billing.write', 'billing.discharge', 'billing.override'
  );
