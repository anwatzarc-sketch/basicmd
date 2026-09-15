-- =====================================================================
--  004 - Clinical documentation
-- =====================================================================
--  clinical_notes, diagnostic_orders, e_prescriptions (FRS 6.1-6.3), all
--  hanging off an encounter rather than an appointment - a booking is a
--  scheduling fact, a clinical note is a fact about the visit itself,
--  and only the encounter model (Stage 1) has a row for that.
--
--  content_encrypted / results_payload_encrypted go through
--  Infrastructure/Security/Encryptor.php (AES-256-GCM) - see that class's
--  own docblock. Nothing here enforces encryption at the database level
--  (a TEXT column cannot), so ClinicalNoteRepository/DiagnosticOrderRepository
--  are the only code allowed to write to these two columns, exactly the
--  same discipline PatientRepository already applies to
--  patients.allergies_encrypted.
--
--  Primary keys are BIGINT UNSIGNED, matching the rest of this schema
--  (see 003's migration comment for why that beats the FRS's literal
--  field-table types where they disagree).
-- =====================================================================

-- ---------------------------------------------------------------------
--  clinical_notes
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `clinical_notes` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `encounter_id`        BIGINT UNSIGNED NOT NULL,
  `author_id`           BIGINT UNSIGNED NOT NULL,
  `note_type`           ENUM('triage','vitals','chief_complaint','progress_note','discharge_summary') NOT NULL,
  -- Structured vitals (bp_systolic, bp_diastolic, hr, temp_c, spo2,
  -- weight_kg - FRS 6.1's worked example). Not clinical free text, so it
  -- is NOT encrypted - a blood pressure reading in isolation identifies
  -- no one the way a written note can.
  `vitals_json`         JSON            NULL,
  `content_encrypted`   TEXT            NOT NULL,
  `icd_code`            VARCHAR(16)     NULL,
  `created_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_clinical_notes_encounter` (`encounter_id`, `created_at`),
  KEY `ix_clinical_notes_author` (`author_id`),
  KEY `ix_clinical_notes_type` (`note_type`),
  CONSTRAINT `fk_clinical_notes_encounter` FOREIGN KEY (`encounter_id`) REFERENCES `encounters` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_clinical_notes_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `ck_clinical_notes_vitals_json` CHECK (`vitals_json` IS NULL OR JSON_VALID(`vitals_json`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  diagnostic_orders
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `diagnostic_orders` (
  `id`                          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `encounter_id`                BIGINT UNSIGNED NOT NULL,
  `ordering_physician_id`       BIGINT UNSIGNED NOT NULL,
  `category`                    ENUM('Lab','Imaging','PACS') NOT NULL,
  `test_code`                   VARCHAR(32)     NOT NULL,
  `test_name`                   VARCHAR(190)    NOT NULL,
  `icd_code`                    VARCHAR(16)     NOT NULL,
  `status`                      ENUM('ORDERED','IN_PROGRESS','COMPLETED','CANCELLED') NOT NULL DEFAULT 'ORDERED',
  `results_payload_encrypted`   TEXT            NULL,
  `created_at`                  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`                  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_diagnostic_orders_encounter` (`encounter_id`, `created_at`),
  KEY `ix_diagnostic_orders_physician` (`ordering_physician_id`),
  KEY `ix_diagnostic_orders_status` (`status`),
  KEY `ix_diagnostic_orders_category` (`category`),
  CONSTRAINT `fk_diagnostic_orders_encounter` FOREIGN KEY (`encounter_id`) REFERENCES `encounters` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_diagnostic_orders_physician` FOREIGN KEY (`ordering_physician_id`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  e_prescriptions
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `e_prescriptions` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `encounter_id`     BIGINT UNSIGNED NOT NULL,
  `prescriber_id`    BIGINT UNSIGNED NOT NULL,
  `icd_code`         VARCHAR(16)     NOT NULL,
  `medication_name`  VARCHAR(190)    NOT NULL,
  `dosage`           VARCHAR(80)     NOT NULL,
  `frequency`        VARCHAR(80)     NOT NULL,
  `duration_days`    SMALLINT UNSIGNED NOT NULL,
  `is_dispensed`     TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_e_prescriptions_encounter` (`encounter_id`, `created_at`),
  KEY `ix_e_prescriptions_prescriber` (`prescriber_id`),
  KEY `ix_e_prescriptions_dispensed` (`is_dispensed`),
  CONSTRAINT `fk_e_prescriptions_encounter` FOREIGN KEY (`encounter_id`) REFERENCES `encounters` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_e_prescriptions_prescriber` FOREIGN KEY (`prescriber_id`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `ck_e_prescriptions_duration` CHECK (`duration_days` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
