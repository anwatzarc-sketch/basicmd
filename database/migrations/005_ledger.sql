-- =====================================================================
--  005 - Financial ledger: consumption_ledger, receivable_payments
-- =====================================================================
--  FRS 7.1/7.2 with the smallest compatible mechanism for FRS 7.3's
--  immutability requirement, which the FRS's own supplied DDL does not
--  actually implement (its own section 7.3 says so explicitly: "The
--  supplied DDL... does not contain parent_entry_id or a
--  verification-status field. The implementation must reconcile the
--  immutable-ledger rule with the supplied DDL through the smallest
--  necessary compatibility-preserving mechanism.")
--
--  The mechanism: ONE nullable self-referencing parent_entry_id per
--  table. A correction is a NEW row - a contra entry - pointing at the
--  original via parent_entry_id, carrying a NEGATIVE amount. Nothing is
--  ever UPDATEd or DELETEd; SUM() over all rows nets a corrected entry to
--  zero while both the original and the correction remain permanently
--  readable. A CHECK enforces that a negative amount can only appear on
--  a row that HAS a parent - an original charge or an original payment
--  can never itself be negative, only its explicit reversal can be.
--  Verified directly against this server: GENERATED ALWAYS AS
--  (unit * per_unit_cost) STORED computes correctly with a negative
--  per_unit_cost, SUM() nets a reversed pair to exactly zero, and the
--  sign-guard CHECK genuinely rejects a negative amount with no parent.
--
--  "Verified payment" (the balance formula's own term, FRS 8.3) needs no
--  separate status column here the way the EXISTING payments table has
--  one (submitted -> verified -> ...): every receivable_payments row is
--  entered directly by an accountant (accountant_id NOT NULL), so
--  posting IS verification for this table - there is no patient-uploaded
--  proof awaiting review the way the pre-Phase-II payment flow has.
--  BillingService::calculateReceivableBalance() therefore sums every row
--  in this table, full stop.
--
--  Primary keys are BIGINT UNSIGNED, matching the rest of this schema
--  (see 003's migration comment for why that beats the FRS's literal
--  field-table types where they disagree).
-- =====================================================================

-- ---------------------------------------------------------------------
--  consumption_ledger
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `consumption_ledger` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `encounter_id`   BIGINT UNSIGNED NOT NULL,
  `accountant_id`  BIGINT UNSIGNED NOT NULL,
  `entry_date`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `category`       ENUM('Registration','Consultation','Imaging','Lab','Pharmacy','Room Fee','Surgical') NOT NULL,
  `cost_entry`     VARCHAR(190)    NOT NULL,
  `unit`           SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  -- Signed: a positive per_unit_cost on an original charge, a negative
  -- one on its correcting contra entry (parent_entry_id NOT NULL). See
  -- ck_consumption_ledger_sign below.
  `per_unit_cost`  DECIMAL(10,2)   NOT NULL,
  `total_cost`     DECIMAL(12,2)   GENERATED ALWAYS AS (`unit` * `per_unit_cost`) STORED,
  `reason`         VARCHAR(255)    NOT NULL,
  `mode`           ENUM('OPD','IPD') NOT NULL,
  `parent_entry_id` BIGINT UNSIGNED NULL,
  `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_consumption_ledger_encounter` (`encounter_id`, `created_at`),
  KEY `ix_consumption_ledger_accountant` (`accountant_id`),
  KEY `ix_consumption_ledger_category` (`category`),
  KEY `ix_consumption_ledger_parent` (`parent_entry_id`),
  CONSTRAINT `fk_consumption_ledger_encounter` FOREIGN KEY (`encounter_id`) REFERENCES `encounters` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_consumption_ledger_accountant` FOREIGN KEY (`accountant_id`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_consumption_ledger_parent` FOREIGN KEY (`parent_entry_id`) REFERENCES `consumption_ledger` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `ck_consumption_ledger_unit` CHECK (`unit` > 0),
  CONSTRAINT `ck_consumption_ledger_sign` CHECK (`per_unit_cost` >= 0 OR `parent_entry_id` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  receivable_payments
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `receivable_payments` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `receipt_id`       VARCHAR(24)     NOT NULL,
  `encounter_id`     BIGINT UNSIGNED NOT NULL,
  `accountant_id`    BIGINT UNSIGNED NOT NULL,
  `payment_date`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  -- Signed for the same reason as consumption_ledger.per_unit_cost - a
  -- refund is a contra row with a negative amount and a parent_entry_id.
  `amount_paid`      DECIMAL(10,2)   NOT NULL,
  `payment_method`   ENUM('Cash','Mobile Money','Bank Transfer','Insurance') NOT NULL,
  `reference_note`   VARCHAR(255)    NULL,
  `parent_entry_id`  BIGINT UNSIGNED NULL,
  `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_receivable_payments_receipt` (`receipt_id`),
  KEY `ix_receivable_payments_encounter` (`encounter_id`, `created_at`),
  KEY `ix_receivable_payments_accountant` (`accountant_id`),
  KEY `ix_receivable_payments_parent` (`parent_entry_id`),
  CONSTRAINT `fk_receivable_payments_encounter` FOREIGN KEY (`encounter_id`) REFERENCES `encounters` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_receivable_payments_accountant` FOREIGN KEY (`accountant_id`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_receivable_payments_parent` FOREIGN KEY (`parent_entry_id`) REFERENCES `receivable_payments` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `ck_receivable_payments_sign` CHECK (`amount_paid` >= 0 OR `parent_entry_id` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
