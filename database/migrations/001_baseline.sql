-- =====================================================================
--  001 - Baseline
-- =====================================================================
--  Marks the pre-existing 16-table schema as the migration-tracked
--  starting point. Every statement here is CREATE TABLE IF NOT EXISTS,
--  so:
--
--   - On a database that already has these tables (every install that
--     predates the migration runner), this migration is a fast no-op per
--     statement and simply gets recorded - nothing is altered.
--   - On a brand-new database with nothing in it, this migration builds
--     the full schema from scratch, so `php bin/migrate.php` alone is
--     enough to provision a database with no separate schema.sql step.
--
--  schema.sql remains authoritative for what a fresh install looks like
--  and is what bin/install.php still applies directly for a first-time
--  setup (cheaper than running every migration in sequence). This file
--  is a byte-for-byte extraction of schema.sql's sixteen CREATE TABLE
--  statements, generated once, then hand-verified against it - the two
--  must be kept in agreement if schema.sql's baseline tables ever change,
--  though from this point on new columns belong in a NEW migration, not
--  edited into schema.sql or into this file.
--
--  Deliberately excluded: CREATE DATABASE, SET NAMES/time_zone, and every
--  DROP TABLE IF EXISTS from schema.sql - a migration must never contain a
--  destructive statement that could run against a database already
--  holding real bookings.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `users` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name`       VARCHAR(160)    NOT NULL,
  `email`           VARCHAR(190)    NOT NULL,
  `phone`           VARCHAR(32)     NULL,
    `password_hash`   VARCHAR(255)    NOT NULL,
  `role`            ENUM('super_admin','doctor','receptionist','finance') NOT NULL DEFAULT 'receptionist',
  `status`          ENUM('active','suspended','invited')                  NOT NULL DEFAULT 'invited',
  `locale`          ENUM('en','am')  NOT NULL DEFAULT 'en',
      `failed_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `locked_until`    DATETIME        NULL,
  `last_login_at`   DATETIME        NULL,
  `last_login_ip`   VARBINARY(16)   NULL,
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`      DATETIME        NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `ix_users_role_status` (`role`, `status`),
  KEY `ix_users_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `doctors` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`          BIGINT UNSIGNED NULL,
  `full_name`        VARCHAR(160)    NOT NULL,
  `full_name_am`     VARCHAR(160)    NULL,
  `slug`             VARCHAR(180)    NOT NULL,
  `specialty`        VARCHAR(120)    NOT NULL,
  `specialty_am`     VARCHAR(120)    NULL,
  `experience_years` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `credentials`      VARCHAR(190)    NULL,
  `bio`              TEXT            NULL,
  `bio_am`           TEXT            NULL,
  `photo_path`       VARCHAR(255)    NULL,
  `initials`         VARCHAR(4)      NOT NULL DEFAULT '',
  `phone`            VARCHAR(32)     NULL,
      `daily_capacity`   SMALLINT UNSIGNED NOT NULL DEFAULT 16,
  `slot_capacity`    SMALLINT UNSIGNED NOT NULL DEFAULT 4,
  `consultation_fee` DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  `status`           ENUM('active','inactive','on_leave') NOT NULL DEFAULT 'active',
  `sort_order`       SMALLINT        NOT NULL DEFAULT 0,
  `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`       DATETIME        NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_doctors_slug` (`slug`),
  UNIQUE KEY `uq_doctors_user` (`user_id`),
  KEY `ix_doctors_status_sort` (`status`, `sort_order`),
  KEY `ix_doctors_specialty` (`specialty`),
  FULLTEXT KEY `ft_doctors` (`full_name`, `specialty`, `credentials`),
  CONSTRAINT `fk_doctors_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `services` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `icon`           VARCHAR(16)     NOT NULL DEFAULT '',
  `name`           VARCHAR(140)    NOT NULL,
  `name_am`        VARCHAR(140)    NULL,
  `slug`           VARCHAR(160)    NOT NULL,
  `description`    TEXT            NULL,
  `description_am` TEXT            NULL,
  `category`       ENUM('clinical','diagnostics','imaging','pharmacy','wellness','emergency') NOT NULL DEFAULT 'clinical',
  `price`          DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  `duration_min`   SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  `is_featured`    TINYINT(1)      NOT NULL DEFAULT 0,
  `status`         ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `sort_order`     SMALLINT        NOT NULL DEFAULT 0,
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`     DATETIME        NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_services_slug` (`slug`),
  KEY `ix_services_status_sort` (`status`, `sort_order`),
  KEY `ix_services_category` (`category`, `status`),
  KEY `ix_services_featured` (`is_featured`, `status`),
  FULLTEXT KEY `ft_services` (`name`, `description`),
  CONSTRAINT `ck_services_price` CHECK (`price` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `facilities` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`           VARCHAR(160)    NOT NULL,
  `name_am`        VARCHAR(160)    NULL,
  `type`           VARCHAR(80)     NOT NULL,
  `room_label`     VARCHAR(80)     NULL,
  `description`    TEXT            NULL,
  `description_am` TEXT            NULL,
  `image_path`     VARCHAR(255)    NULL,
  `status`         ENUM('operational','maintenance','upgrading','offline') NOT NULL DEFAULT 'operational',
  `notes`          VARCHAR(500)    NULL,
  `is_public`      TINYINT(1)      NOT NULL DEFAULT 1,
  `sort_order`     SMALLINT        NOT NULL DEFAULT 0,
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`     DATETIME        NULL,
  PRIMARY KEY (`id`),
  KEY `ix_facilities_status` (`status`, `sort_order`),
  KEY `ix_facilities_public` (`is_public`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `health_packages` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`          VARCHAR(140)    NOT NULL,
  `title_am`       VARCHAR(140)    NULL,
  `slug`           VARCHAR(160)    NOT NULL,
  `price_etb`      DECIMAL(10,2)   NOT NULL,
    `deposit_rate`   DECIMAL(4,3)    NOT NULL DEFAULT 0.300,
  `description`    TEXT            NULL,
  `description_am` TEXT            NULL,
    `items_json`     JSON            NULL,
  `badge`          VARCHAR(40)     NULL,
  `is_featured`    TINYINT(1)      NOT NULL DEFAULT 0,
  `status`         ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `sort_order`     SMALLINT        NOT NULL DEFAULT 0,
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`     DATETIME        NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_packages_slug` (`slug`),
  KEY `ix_packages_status_sort` (`status`, `sort_order`),
  CONSTRAINT `ck_packages_price`   CHECK (`price_etb` >= 0),
  CONSTRAINT `ck_packages_deposit` CHECK (`deposit_rate` BETWEEN 0 AND 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payment_methods` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `channel`           ENUM('bank_transfer','mobile_money','cash_on_arrival') NOT NULL DEFAULT 'bank_transfer',
  `provider`          VARCHAR(80)     NOT NULL,
  `provider_am`       VARCHAR(80)     NULL,
  `account_name`      VARCHAR(160)    NULL,
  `account_number`    VARCHAR(80)     NULL,
  `branch`            VARCHAR(120)    NULL,
  `logo_path`         VARCHAR(255)    NULL,
  `instructions`      TEXT            NULL,
  `instructions_am`   TEXT            NULL,
  `requires_proof`    TINYINT(1)      NOT NULL DEFAULT 1,
  `status`            ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `sort_order`        SMALLINT        NOT NULL DEFAULT 0,
  `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`        DATETIME        NULL,
  PRIMARY KEY (`id`),
  KEY `ix_paymethods_status` (`status`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `doctor_time_off` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doctor_id`  BIGINT UNSIGNED NOT NULL,
  `starts_on`  DATE            NOT NULL,
  `ends_on`    DATE            NOT NULL,
  `reason`     VARCHAR(190)    NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_timeoff_doctor_range` (`doctor_id`, `starts_on`, `ends_on`),
  CONSTRAINT `fk_timeoff_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_timeoff_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `ck_timeoff_range` CHECK (`ends_on` >= `starts_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `appointments` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_ref`      CHAR(12)        NOT NULL,
  `patient_name`     VARCHAR(160)    NOT NULL,
  `patient_phone`    VARCHAR(32)     NOT NULL,
  `patient_email`    VARCHAR(190)    NULL,
  `patient_notes`    TEXT            NULL,
  `service_id`       BIGINT UNSIGNED NULL,
  `package_id`       BIGINT UNSIGNED NULL,
  `doctor_id`        BIGINT UNSIGNED NULL,
  `appointment_date` DATE            NOT NULL,
  `time_slot`        ENUM('08:00-10:00','10:00-12:00','14:00-16:00','16:00-18:00') NOT NULL,
  `queue_tier`       ENUM('standard','express') NOT NULL DEFAULT 'standard',
  `status`           ENUM('pending','confirmed','completed','cancelled','no_show') NOT NULL DEFAULT 'pending',
  `payment_status`   ENUM('unpaid','awaiting_verification','deposit_paid','paid','refunded','waived') NOT NULL DEFAULT 'unpaid',
  `base_amount`      DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  `surcharge_amount` DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  `total_amount`     DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  `amount_paid`      DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  `payment_ref`      VARCHAR(80)     NULL,
  `source`           ENUM('web','admin','phone','walk_in') NOT NULL DEFAULT 'web',
  `locale`           ENUM('en','am') NOT NULL DEFAULT 'en',
  `confirmed_at`     DATETIME        NULL,
  `completed_at`     DATETIME        NULL,
  `cancelled_at`     DATETIME        NULL,
  `cancel_reason`    VARCHAR(255)    NULL,
  `reminder_sent_at` DATETIME        NULL,
  `followup_sent_at` DATETIME        NULL,
  `handled_by`       BIGINT UNSIGNED NULL,
  `ip_address`       VARBINARY(16)   NULL,
  `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`       DATETIME        NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_appointments_ref` (`booking_ref`),
    UNIQUE KEY `uq_appointments_slot_guard` (`doctor_id`, `appointment_date`, `time_slot`, `patient_phone`),
  KEY `ix_appointments_date_status`   (`appointment_date`, `status`),
  KEY `ix_appointments_doctor_date`   (`doctor_id`, `appointment_date`, `status`),
  KEY `ix_appointments_capacity`      (`doctor_id`, `appointment_date`, `time_slot`, `status`),
  KEY `ix_appointments_payment`       (`payment_status`, `created_at`),
  KEY `ix_appointments_phone`         (`patient_phone`),
  KEY `ix_appointments_reminder`      (`appointment_date`, `status`, `reminder_sent_at`),
  KEY `ix_appointments_created`       (`created_at`),
  CONSTRAINT `fk_appt_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_appt_package` FOREIGN KEY (`package_id`) REFERENCES `health_packages` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_appt_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_appt_handler` FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `ck_appt_amounts` CHECK (
    `base_amount` >= 0 AND `surcharge_amount` >= 0
    AND `total_amount` >= 0 AND `amount_paid` >= 0
  ),
    CONSTRAINT `ck_appt_subject` CHECK (`service_id` IS NOT NULL OR `package_id` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payments` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `appointment_id`    BIGINT UNSIGNED NOT NULL,
  `payment_method_id` BIGINT UNSIGNED NULL,
  `kind`              ENUM('deposit','balance','full') NOT NULL DEFAULT 'full',
  `amount`            DECIMAL(10,2)   NOT NULL,
  `currency`          CHAR(3)         NOT NULL DEFAULT 'ETB',
  `payer_name`        VARCHAR(160)    NULL,
  `transfer_ref`      VARCHAR(120)    NULL,
  `transferred_at`    DATE            NULL,
      `proof_path`        VARCHAR(255)    NULL,
  `proof_original`    VARCHAR(255)    NULL,
  `proof_mime`        VARCHAR(80)     NULL,
  `proof_size`        INT UNSIGNED    NULL,
  `proof_sha256`      CHAR(64)        NULL,
  `status`            ENUM('awaiting_proof','submitted','verified','rejected') NOT NULL DEFAULT 'awaiting_proof',
  `verified_by`       BIGINT UNSIGNED NULL,
  `verified_at`       DATETIME        NULL,
  `rejection_reason`  VARCHAR(500)    NULL,
  `admin_note`        VARCHAR(500)    NULL,
  `submitted_ip`      VARBINARY(16)   NULL,
  `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_payments_appt`   (`appointment_id`, `status`),
  KEY `ix_payments_status` (`status`, `created_at`),
  KEY `ix_payments_sha`    (`proof_sha256`),
  KEY `ix_payments_verifier` (`verified_by`),
  CONSTRAINT `fk_pay_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pay_method` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pay_verifier` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `ck_pay_amount` CHECK (`amount` > 0),
    CONSTRAINT `ck_pay_rejection` CHECK (`status` <> 'rejected' OR `rejection_reason` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `articles` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`            VARCHAR(220)    NOT NULL,
  `title_am`         VARCHAR(220)    NULL,
  `slug`             VARCHAR(240)    NOT NULL,
  `category`         VARCHAR(80)     NOT NULL DEFAULT 'General',
  `excerpt`          VARCHAR(500)    NULL,
  `excerpt_am`       VARCHAR(500)    NULL,
  `content`          MEDIUMTEXT      NULL,
  `content_am`       MEDIUMTEXT      NULL,
  `cover_image`      VARCHAR(255)    NULL,
  `cover_alt`        VARCHAR(190)    NULL,
  `author_id`        BIGINT UNSIGNED NULL,
  `reviewer_id`      BIGINT UNSIGNED NULL,
  `schema_type`      ENUM('MedicalWebPage','MedicalCondition','MedicalProcedure','Article') NOT NULL DEFAULT 'MedicalWebPage',
  `meta_title`       VARCHAR(190)    NULL,
  `meta_description` VARCHAR(320)    NULL,
  `focus_keyword`    VARCHAR(120)    NULL,
  `read_minutes`     TINYINT UNSIGNED NOT NULL DEFAULT 3,
  `views`            INT UNSIGNED    NOT NULL DEFAULT 0,
  `status`           ENUM('draft','review','published','archived') NOT NULL DEFAULT 'draft',
  `published_at`     DATETIME        NULL,
  `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`       DATETIME        NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_articles_slug` (`slug`),
  KEY `ix_articles_status_pub` (`status`, `published_at`),
  KEY `ix_articles_category`   (`category`, `status`),
  KEY `ix_articles_author`     (`author_id`),
  KEY `ix_articles_reviewer`   (`reviewer_id`),
  FULLTEXT KEY `ft_articles` (`title`, `excerpt`, `content`),
  CONSTRAINT `fk_articles_author` FOREIGN KEY (`author_id`) REFERENCES `doctors` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_articles_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `doctors` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `ck_articles_published` CHECK (`status` <> 'published' OR `published_at` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `contact_inquiries` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(160)    NOT NULL,
  `phone`        VARCHAR(32)     NOT NULL,
  `email`        VARCHAR(190)    NULL,
  `subject`      VARCHAR(190)    NULL,
  `message`      TEXT            NOT NULL,
  `status`       ENUM('unread','in_progress','responded','archived','spam') NOT NULL DEFAULT 'unread',
  `notes`        TEXT            NULL,
  `handled_by`   BIGINT UNSIGNED NULL,
  `responded_at` DATETIME        NULL,
  `locale`       ENUM('en','am') NOT NULL DEFAULT 'en',
  `ip_address`   VARBINARY(16)   NULL,
  `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`   DATETIME        NULL,
  PRIMARY KEY (`id`),
  KEY `ix_inq_status` (`status`, `created_at`),
  KEY `ix_inq_handler` (`handled_by`),
  CONSTRAINT `fk_inq_handler` FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_outbox` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mailable`      VARCHAR(80)     NOT NULL,
  `to_email`      VARCHAR(190)    NOT NULL,
  `to_name`       VARCHAR(160)    NULL,
  `subject`       VARCHAR(255)    NOT NULL,
  `body_html`     MEDIUMTEXT      NOT NULL,
  `body_text`     MEDIUMTEXT      NULL,
  `context_json`  JSON            NULL,
  `related_type`  VARCHAR(40)     NULL,
  `related_id`    BIGINT UNSIGNED NULL,
  `status`        ENUM('queued','sending','sent','failed') NOT NULL DEFAULT 'queued',
  `attempts`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `max_attempts`  TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `available_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `locked_at`     DATETIME        NULL,
  `sent_at`       DATETIME        NULL,
  `last_error`    VARCHAR(1000)   NULL,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_outbox_claim`   (`status`, `available_at`),
  KEY `ix_outbox_related` (`related_type`, `related_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED NULL,
  `actor_label` VARCHAR(160)    NULL,
  `action`      VARCHAR(80)     NOT NULL,
  `target_type` VARCHAR(60)     NULL,
  `target_id`   BIGINT UNSIGNED NULL,
  `summary`     VARCHAR(500)    NULL,
  `changes_json` JSON           NULL,
  `ip_address`  VARBINARY(16)   NULL,
  `user_agent`  VARCHAR(255)    NULL,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_audit_user`   (`user_id`, `created_at`),
  KEY `ix_audit_target` (`target_type`, `target_id`),
  KEY `ix_audit_action` (`action`, `created_at`),
  KEY `ix_audit_created` (`created_at`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bucket_key`   VARCHAR(190)    NOT NULL,
  `hits`         INT UNSIGNED    NOT NULL DEFAULT 0,
  `window_start` DATETIME        NOT NULL,
  `expires_at`   DATETIME        NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rate_bucket` (`bucket_key`),
  KEY `ix_rate_expiry` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `token_hash` CHAR(64)        NOT NULL,
  `expires_at` DATETIME        NOT NULL,
  `used_at`    DATETIME        NULL,
  `request_ip` VARBINARY(16)   NULL,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reset_token` (`token_hash`),
  KEY `ix_reset_user` (`user_id`, `expires_at`),
  CONSTRAINT `fk_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `system_settings` (
  `setting_key`  VARCHAR(80)  NOT NULL,
  `value`        TEXT         NULL,
  `group_name`   VARCHAR(40)  NOT NULL DEFAULT 'general',
  `value_type`   ENUM('string','int','bool','json','text') NOT NULL DEFAULT 'string',
  `label`        VARCHAR(160) NULL,
  `is_public`    TINYINT(1)   NOT NULL DEFAULT 0,
  `updated_by`   BIGINT UNSIGNED NULL,
  `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`),
  KEY `ix_settings_group` (`group_name`),
  CONSTRAINT `fk_settings_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
