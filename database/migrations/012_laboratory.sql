-- =====================================================================
--  012 - Laboratory: test catalogue, requisition metadata, results
-- =====================================================================
--  The Lab screens (work queue, result entry, printed report sheet,
--  master test directory) sit on top of the `diagnostic_orders` table
--  migration 004 already created. A lab order IS a diagnostic order with
--  category = 'Lab' - there is no second order table here, because an
--  order's identity, its encounter, its ordering physician and its
--  status machine are all already modelled, and duplicating them would
--  immediately raise the question of which copy is true.
--
--  What a laboratory needs beyond that is three things:
--
--   1. A CATALOGUE of what can be tested, so a technician picks a panel
--      instead of retyping nine parameter names, nine units and
--      eighteen reference bounds per patient. That is `lab_panels` +
--      `lab_panel_parameters`.
--
--   2. SPECIMEN metadata on the order itself - what was drawn, when,
--      from which ward, under which barcode. Collection is a fact about
--      this order, not a second entity, so it lives as columns here.
--
--   3. Structured RESULTS. These deliberately do NOT get a table. A
--      result value is the most sensitive field in this schema, and
--      `diagnostic_orders.results_payload_encrypted` is already the one
--      encrypted-at-rest home for it (004's docblock, and Encryptor's).
--      The result matrix is therefore serialised to JSON and encrypted
--      into that same column, so structured entry costs nothing in
--      confidentiality: a database dump still yields ciphertext, and
--      DiagnosticOrderRepository remains the only code that can read it.
--      A per-parameter table would have put haemoglobin values in
--      plaintext columns to buy a reporting capability nothing asks for.
--
--  Reference bounds, units and methodology are NOT patient data and DO
--  live in plaintext - in the catalogue, and copied into the encrypted
--  payload beside each value so a report reprinted years later shows the
--  interval that was actually in force when it was signed, rather than
--  whatever the catalogue happens to say by then.
--
--  DEPLOY ORDER: this migration must run BEFORE the matching code. The
--  lab screens select the new columns explicitly.
-- =====================================================================

-- ---------------------------------------------------------------------
--  lab_panels - one row per orderable panel ("CBC with Differential")
--
--  panel_code is the stable key the order rows carry, so renaming a
--  panel for the report header never orphans the orders placed under it.
--  No default_remarks column exists, by deliberate omission: a canned
--  diagnostic impression pre-filled into the pathologist comment box is
--  text that gets signed without being written, which is the exact
--  failure a signature block exists to prevent.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `lab_panels` (
  `id`            BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `panel_code`    VARCHAR(32)       NOT NULL,
  `panel_name`    VARCHAR(190)      NOT NULL,
  `department`    VARCHAR(80)       NOT NULL,
  `report_title`  VARCHAR(190)      NOT NULL,
  `specimen_type` VARCHAR(80)       NOT NULL,
  `methodology`   VARCHAR(190)      NULL,
  `is_active`     TINYINT(1)        NOT NULL DEFAULT 1,
  `sort_order`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lab_panels_code` (`panel_code`),
  KEY `ix_lab_panels_listing` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  lab_panel_parameters - the analytes inside a panel
--
--  ref_min/ref_max NULL means the parameter is QUALITATIVE (urine
--  protein, nitrite, colour) and ref_text carries what normal reads as
--  ("Negative", "Yellow / Clear"). This is why there is no 0/0 sentinel
--  pair: "no numeric interval" and "an interval of zero to zero" are
--  different statements, and a flag engine that cannot tell them apart
--  calls every negative dipstick normal by accident rather than on
--  purpose.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `lab_panel_parameters` (
  `id`             BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `panel_id`       BIGINT UNSIGNED   NOT NULL,
  `parameter_name` VARCHAR(190)      NOT NULL,
  `unit`           VARCHAR(40)       NOT NULL DEFAULT '',
  `ref_min`        DECIMAL(14,4)     NULL,
  `ref_max`        DECIMAL(14,4)     NULL,
  `ref_text`       VARCHAR(80)       NULL,
  `methodology`    VARCHAR(120)      NOT NULL DEFAULT '',
  `sort_order`     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `is_active`      TINYINT(1)        NOT NULL DEFAULT 1,
  `created_at`     DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lab_panel_parameters_name` (`panel_id`, `parameter_name`),
  KEY `ix_lab_panel_parameters_order` (`panel_id`, `sort_order`),
  CONSTRAINT `fk_lab_panel_parameters_panel` FOREIGN KEY (`panel_id`) REFERENCES `lab_panels` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ck_lab_panel_parameters_interval` CHECK (
    `ref_min` IS NULL OR `ref_max` IS NULL OR `ref_min` <= `ref_max`
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  diagnostic_orders - specimen and result-authorship columns
--
--  accession_number (LAB-YYYYMMDD-00001) is the laboratory's own
--  identifier for the requisition, issued from NumberSequence the same
--  collision-safe way VisitNumber and ReceiptId are. It is NULL for
--  every order placed before this migration and for Imaging/PACS orders,
--  which is why the UNIQUE key sits on a nullable column - MySQL permits
--  any number of NULLs under a unique index, so backfilling is optional
--  rather than a precondition for deploying.
--
--  resulted_by_id is ON DELETE SET NULL, unlike ordering_physician_id's
--  RESTRICT. The distinction is deliberate: who ORDERED a test is part
--  of the clinical record and must never become unattributable, while
--  who keyed the numbers in is operational provenance - and
--  PlatformReset deletes users wholesale before launch, which a RESTRICT
--  here would block.
-- ---------------------------------------------------------------------

ALTER TABLE `diagnostic_orders`
  ADD COLUMN `accession_number`  VARCHAR(32)     NULL AFTER `id`,
  ADD COLUMN `panel_code`        VARCHAR(32)     NULL AFTER `test_name`,
  ADD COLUMN `specimen_type`     VARCHAR(80)     NULL AFTER `panel_code`,
  ADD COLUMN `specimen_barcode`  VARCHAR(40)     NULL AFTER `specimen_type`,
  ADD COLUMN `collected_at`      DATETIME        NULL AFTER `specimen_barcode`,
  ADD COLUMN `clinical_location` VARCHAR(120)    NULL AFTER `collected_at`,
  ADD COLUMN `resulted_by_id`    BIGINT UNSIGNED NULL AFTER `results_payload_encrypted`,
  ADD COLUMN `resulted_at`       DATETIME        NULL AFTER `resulted_by_id`,
  ADD UNIQUE KEY `uq_diagnostic_orders_accession` (`accession_number`),
  ADD UNIQUE KEY `uq_diagnostic_orders_barcode` (`specimen_barcode`),
  ADD KEY `ix_diagnostic_orders_panel` (`panel_code`),
  ADD KEY `ix_diagnostic_orders_resulted_by` (`resulted_by_id`),
  ADD CONSTRAINT `fk_diagnostic_orders_resulted_by` FOREIGN KEY (`resulted_by_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

-- ---------------------------------------------------------------------
--  lab_catalog.manage - super_admin only, same shape as migration 010
--
--  Reading the directory needs no new permission: it is gated on
--  diagnostics.view, which every role that can see an order already
--  holds. Editing a reference interval changes what the flag engine
--  calls abnormal on every future report, so it is administrative rather
--  than clinical, and is not delegated by default.
-- ---------------------------------------------------------------------

INSERT INTO `permissions` (`slug`) VALUES ('lab_catalog.manage');

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
WHERE r.slug = 'super_admin' AND p.slug = 'lab_catalog.manage';

-- ---------------------------------------------------------------------
--  Seed catalogue - five routine panels
--
--  INSERT IGNORE throughout, keyed on panel_code and (panel_id,
--  parameter_name), so re-running against a site that has already edited
--  its catalogue adds nothing and overwrites nothing. These are shipped
--  defaults, editable in the admin: reference intervals are
--  method-dependent and population-dependent, and every laboratory is
--  expected to reconcile them against its own analysers before use.
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `lab_panels`
  (`panel_code`, `panel_name`, `department`, `report_title`, `specimen_type`, `methodology`, `sort_order`) VALUES
  ('CBC',   'Complete Blood Count (CBC)',      'Haematology',       'Haematology - Complete Blood Count (CBC) with Differential', 'Whole Blood (EDTA)', 'Automated haematology analyser', 10),
  ('LIPID', 'Lipid & Cardiac Profile',         'Clinical Chemistry', 'Clinical Chemistry - Lipid & Cardiovascular Risk Profile',  'Serum (SST Tube)',   'Enzymatic colorimetric',        20),
  ('CMP',   'Comprehensive Metabolic Panel',   'Clinical Chemistry', 'Clinical Chemistry - Comprehensive Metabolic Panel (CMP)',  'Serum (SST Tube)',   'Photometric / ISE',             30),
  ('TFT',   'Thyroid Function Profile',        'Endocrinology',      'Endocrinology - Comprehensive Thyroid Function Profile',    'Serum (SST Tube)',   'ECLIA chemiluminescence',       40),
  ('UA',    'Complete Urinalysis',             'Clinical Pathology', 'Urinalysis - Complete Chemical & Microscopic Examination',  'Random Urine',       'Reagent strip / microscopy',    50);

INSERT IGNORE INTO `lab_panel_parameters`
  (`panel_id`, `parameter_name`, `unit`, `ref_min`, `ref_max`, `ref_text`, `methodology`, `sort_order`)
SELECT p.id, v.parameter_name, v.unit, v.ref_min, v.ref_max, v.ref_text, v.methodology, v.sort_order
FROM `lab_panels` p
JOIN (
  SELECT 'CBC' AS panel_code, 'Haemoglobin (Hgb)'               AS parameter_name, 'g/dL'         AS unit, 12.0000 AS ref_min, 16.0000 AS ref_max, NULL AS ref_text, 'Spectrophotometry'       AS methodology, 10 AS sort_order UNION ALL
  SELECT 'CBC', 'Haematocrit (Hct)',              '%',            36.0000,  48.0000, NULL, 'Calculated',               20 UNION ALL
  SELECT 'CBC', 'Red Blood Cell Count (RBC)',     'x10^6/uL',      4.0000,   5.4000, NULL, 'Electrical impedance',     30 UNION ALL
  SELECT 'CBC', 'Mean Corpuscular Volume (MCV)',  'fL',           80.0000, 100.0000, NULL, 'Direct measurement',       40 UNION ALL
  SELECT 'CBC', 'Mean Corpuscular Hgb (MCH)',     'pg',           27.0000,  33.0000, NULL, 'Calculated',               50 UNION ALL
  SELECT 'CBC', 'White Blood Cell Count (WBC)',   'x10^3/uL',      4.5000,  11.0000, NULL, 'Optical flow cytometry',   60 UNION ALL
  SELECT 'CBC', 'Neutrophils',                    '%',            40.0000,  75.0000, NULL, 'Automated differential',   70 UNION ALL
  SELECT 'CBC', 'Lymphocytes',                    '%',            20.0000,  45.0000, NULL, 'Automated differential',   80 UNION ALL
  SELECT 'CBC', 'Platelet Count',                 'x10^3/uL',    150.0000, 450.0000, NULL, 'Impedance',                90 UNION ALL

  SELECT 'LIPID', 'Total Cholesterol',            'mg/dL',       120.0000, 200.0000, NULL, 'Enzymatic colorimetric',   10 UNION ALL
  SELECT 'LIPID', 'Triglycerides',                'mg/dL',        35.0000, 150.0000, NULL, 'Enzymatic',                20 UNION ALL
  SELECT 'LIPID', 'HDL Cholesterol',              'mg/dL',        40.0000,  60.0000, NULL, 'Direct homogeneous',       30 UNION ALL
  SELECT 'LIPID', 'LDL Cholesterol (calculated)', 'mg/dL',        50.0000, 100.0000, NULL, 'Friedewald equation',      40 UNION ALL
  SELECT 'LIPID', 'Non-HDL Cholesterol',          'mg/dL',         0.0000, 130.0000, NULL, 'Calculated',               50 UNION ALL
  SELECT 'LIPID', 'Cholesterol / HDL Ratio',      'ratio',         1.0000,   5.0000, NULL, 'Calculated',               60 UNION ALL

  SELECT 'CMP', 'Serum Glucose (fasting)',        'mg/dL',        70.0000,  99.0000, NULL, 'Glucose oxidase',          10 UNION ALL
  SELECT 'CMP', 'Blood Urea Nitrogen (BUN)',      'mg/dL',         7.0000,  20.0000, NULL, 'Urease-GLDH',              20 UNION ALL
  SELECT 'CMP', 'Serum Creatinine',               'mg/dL',         0.6000,   1.2000, NULL, 'Jaffe rate, modified',     30 UNION ALL
  SELECT 'CMP', 'eGFR (CKD-EPI)',                 'mL/min/1.73m2', 60.0000, 120.0000, NULL, 'Calculated',              40 UNION ALL
  SELECT 'CMP', 'Serum Sodium (Na)',              'mmol/L',      135.0000, 145.0000, NULL, 'ISE indirect',             50 UNION ALL
  SELECT 'CMP', 'Serum Potassium (K)',            'mmol/L',        3.5000,   5.1000, NULL, 'ISE indirect',             60 UNION ALL
  SELECT 'CMP', 'Serum Chloride (Cl)',            'mmol/L',       96.0000, 106.0000, NULL, 'ISE indirect',             70 UNION ALL
  SELECT 'CMP', 'Total Calcium',                  'mg/dL',         8.5000,  10.2000, NULL, 'CPC photometric',          80 UNION ALL

  SELECT 'TFT', 'TSH',                            'uIU/mL',        0.4500,   4.5000, NULL, 'ECLIA chemiluminescence',  10 UNION ALL
  SELECT 'TFT', 'Free T4 (thyroxine)',            'ng/dL',         0.8200,   1.7700, NULL, 'ECLIA',                    20 UNION ALL
  SELECT 'TFT', 'Free T3 (triiodothyronine)',     'pg/mL',         2.0000,   4.4000, NULL, 'ECLIA',                    30 UNION ALL
  SELECT 'TFT', 'Thyroid Peroxidase (TPO) Ab',    'IU/mL',         0.0000,  34.0000, NULL, 'Chemiluminescent immunoassay', 40 UNION ALL

  SELECT 'UA', 'Urine Colour / Clarity',          '',                 NULL,     NULL, 'Yellow / Clear', 'Visual',      10 UNION ALL
  SELECT 'UA', 'Specific Gravity',                '',              1.0050,   1.0300, NULL, 'Refractometry',            20 UNION ALL
  SELECT 'UA', 'Urine pH',                        'pH units',      5.0000,   8.0000, NULL, 'Reagent strip',            30 UNION ALL
  SELECT 'UA', 'Urine Protein',                   'mg/dL',            NULL,     NULL, 'Negative', 'Colorimetric',      40 UNION ALL
  SELECT 'UA', 'Urine Glucose',                   'mg/dL',            NULL,     NULL, 'Negative', 'Enzymatic',         50 UNION ALL
  SELECT 'UA', 'Leukocyte Esterase',              'cells/uL',         NULL,     NULL, 'Negative', 'Reagent strip',      60 UNION ALL
  SELECT 'UA', 'Nitrite',                         '',                 NULL,     NULL, 'Negative', 'Diazo reaction',     70
) v ON v.panel_code = p.panel_code;
