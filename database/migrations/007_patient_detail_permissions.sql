-- =====================================================================
--  007 - Patient Detail permissions (clinical documentation, diagnostics,
--        prescriptions, scoped audit)
-- =====================================================================
--  Migration 002 left `nurse` and `lab_technician` with an empty
--  permission set on purpose: "their own screens (clinical
--  documentation, diagnostic result entry) are not part of this pass,
--  and guessing a permission set for a screen that does not exist yet is
--  exactly what that comment already ruled out."
--
--  Those screens now exist, so this migration gives both roles their
--  first real permissions - including the baseline (dashboard.view,
--  patients.view) they need simply to sign in and reach a patient at
--  all, which neither had before.
--
--  Two deliberate NARROW/BROAD slug pairs appear here:
--
--    clinical_notes.write        - any note_type
--    clinical_notes.write_vitals - vitals/triage only
--
--    diagnostics.result          - any category
--    diagnostics.result_lab      - category = 'Lab' only
--
--  They exist so the nurse's note-type restriction and the lab
--  technician's category scoping are decided by WHICH PERMISSION a user
--  holds, never by comparing users.role in application code. The role
--  enum is a synced cache (see User::resolvePermissions()), not an
--  authority, and a restriction keyed on it could not be granted to a
--  new role without editing PHP.
--
--  Also corrects a seeding inconsistency: physician holds
--  patients.notes but super_admin does not, which contradicts
--  super_admin's own "listed explicitly rather than special-cased"
--  superset convention (UserRole::permissions()).
-- =====================================================================

INSERT INTO `permissions` (`slug`) VALUES
  ('clinical_notes.view'),
  ('clinical_notes.write'),
  ('clinical_notes.write_vitals'),
  ('diagnostics.view'),
  ('diagnostics.order'),
  ('diagnostics.result'),
  ('diagnostics.result_lab'),
  ('prescriptions.view'),
  ('prescriptions.write'),
  ('prescriptions.dispense'),
  ('audit.view_own');

-- ---------------------------------------------------------------------
--  super_admin: the superset, listed explicitly. Includes patients.notes,
--  which it should always have held.
-- ---------------------------------------------------------------------
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
WHERE r.slug = 'super_admin'
  AND p.slug IN (
    'clinical_notes.view', 'clinical_notes.write', 'clinical_notes.write_vitals',
    'diagnostics.view', 'diagnostics.order', 'diagnostics.result', 'diagnostics.result_lab',
    'prescriptions.view', 'prescriptions.write', 'prescriptions.dispense',
    'patients.notes'
  );

-- ---------------------------------------------------------------------
--  physician: the clinical cockpit - documents, orders and prescribes on
--  this patient's record, and sees their OWN audit entries for it
--  (audit.view_own), not the whole trail.
-- ---------------------------------------------------------------------
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
WHERE r.slug = 'physician'
  AND p.slug IN (
    'clinical_notes.view', 'clinical_notes.write',
    'diagnostics.view', 'diagnostics.order',
    'prescriptions.view', 'prescriptions.write', 'prescriptions.dispense',
    'audit.view_own'
  );

-- ---------------------------------------------------------------------
--  nurse: first permissions this role has ever held. Monitoring and
--  vitals capture, not diagnosis or prescription - it reads orders and
--  prescriptions for ward safety context but can create neither.
--  dashboard.view and patients.view are the baseline needed to sign in
--  and open a patient at all.
--
--  prescriptions.dispense is the spec's own option (a) for a gap it
--  names explicitly: e_prescriptions.is_dispensed has no dispensed_by,
--  dispensed_at, batch or expiry column, and no role in this enum
--  represents a pharmacist. Nurse and physician are the closest real
--  fit for a bare toggle; a genuine dispensing workflow needs a
--  pharmacist role and those columns first.
-- ---------------------------------------------------------------------
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
WHERE r.slug = 'nurse'
  AND p.slug IN (
    'dashboard.view',
    'patients.view',
    'encounters.view',
    'clinical_notes.view', 'clinical_notes.write_vitals',
    'diagnostics.view',
    'prescriptions.view', 'prescriptions.dispense'
  );

-- ---------------------------------------------------------------------
--  receptionist: registration and check-in already covered by 006. The
--  only addition is its own audit entries on a patient record - who
--  edited which demographics, answerable without exposing the whole
--  system trail.
-- ---------------------------------------------------------------------
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
WHERE r.slug = 'receptionist'
  AND p.slug IN ('audit.view_own');

-- ---------------------------------------------------------------------
--  lab_technician: first permissions this role has ever held. Scoped to
--  executing Lab orders and nothing else - diagnostics.result_lab rather
--  than diagnostics.result, because the role is literally named for Lab
--  and nothing in this enum represents an Imaging/PACS operator. Those
--  categories stay with the ordering physician and super_admin until a
--  Radiology role exists.
--
--  patients.view is deliberate: reading the patient's identity and
--  allergies is safety context for running a test, and the Patient
--  Detail shell shows this role nothing else.
-- ---------------------------------------------------------------------
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
WHERE r.slug = 'lab_technician'
  AND p.slug IN (
    'dashboard.view',
    'patients.view',
    'diagnostics.view', 'diagnostics.result_lab'
  );

-- accountant gains nothing here by design: 006 already granted the
-- ledger, and the financial panel on the Patient Detail page reads the
-- same billing.* permissions. Clinical documentation stays invisible to
-- it - that separation is the point.
