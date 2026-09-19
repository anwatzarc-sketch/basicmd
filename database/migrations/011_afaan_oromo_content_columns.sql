-- =====================================================================
--  011 - Afaan Oromo (`_om`) content columns
-- =====================================================================
--  Locale::OM ('om') joins EN and AM as a first-class content language.
--  Locale::columnSuffix() is already generic - '' / '_am' / '_om' - so
--  the only thing missing is the storage: every CMS table that carries a
--  `<col>_am` translation column now gets the matching `<col>_om`
--  beside it, with the SAME type and the SAME nullability.
--
--  NULL means "not translated yet", exactly as it does for `_am`. The
--  entities fall back to the base English column in that case, so a
--  half-translated catalogue degrades to readable English rather than to
--  blank cards - and never to the OTHER translation, which would put
--  Ge'ez script in front of an Afaan Oromo reader.
--
--  The `_om` columns are placed AFTER their `_am` sibling so the three
--  variants of one field stay adjacent in a DESCRIBE / admin dump.
--
--  Every column is added in a single ALTER per table: MariaDB rewrites
--  or instant-adds the table once instead of once per column.
--
--  DEPLOY ORDER - this migration must run BEFORE the matching code:
--  the admin save paths write the `_om` columns explicitly, so an admin
--  save against a database that has not been migrated fails on an
--  unknown column. Reads are safe either way: the entities hydrate the
--  `_om` properties with `$row['x_om'] ?? null`, so a pre-migration row
--  simply has no translation.
-- =====================================================================

-- ---------------------------------------------------------------------
--  doctors - name, specialty, biography
-- ---------------------------------------------------------------------
ALTER TABLE `doctors`
  ADD COLUMN `full_name_om` VARCHAR(160) NULL AFTER `full_name_am`,
  ADD COLUMN `specialty_om` VARCHAR(120) NULL AFTER `specialty_am`,
  ADD COLUMN `bio_om`       TEXT         NULL AFTER `bio_am`;

-- ---------------------------------------------------------------------
--  services - catalogue name and description
-- ---------------------------------------------------------------------
ALTER TABLE `services`
  ADD COLUMN `name_om`        VARCHAR(140) NULL AFTER `name_am`,
  ADD COLUMN `description_om` TEXT         NULL AFTER `description_am`;

-- ---------------------------------------------------------------------
--  facilities - room/wing name and description
-- ---------------------------------------------------------------------
ALTER TABLE `facilities`
  ADD COLUMN `name_om`        VARCHAR(160) NULL AFTER `name_am`,
  ADD COLUMN `description_om` TEXT         NULL AFTER `description_am`;

-- ---------------------------------------------------------------------
--  health_packages - title and description.
--
--  The bullet list needs no column: `items_json` is already keyed by
--  locale ({"en":[...],"am":[...]}), so the Afaan Oromo list is simply
--  an "om" key inside the existing JSON.
-- ---------------------------------------------------------------------
ALTER TABLE `health_packages`
  ADD COLUMN `title_om`       VARCHAR(140) NULL AFTER `title_am`,
  ADD COLUMN `description_om` TEXT         NULL AFTER `description_am`;

-- ---------------------------------------------------------------------
--  payment_methods - provider name and transfer instructions
-- ---------------------------------------------------------------------
ALTER TABLE `payment_methods`
  ADD COLUMN `provider_om`     VARCHAR(80) NULL AFTER `provider_am`,
  ADD COLUMN `instructions_om` TEXT        NULL AFTER `instructions_am`;

-- ---------------------------------------------------------------------
--  articles - headline, excerpt and body
-- ---------------------------------------------------------------------
ALTER TABLE `articles`
  ADD COLUMN `title_om`   VARCHAR(220) NULL AFTER `title_am`,
  ADD COLUMN `excerpt_om` VARCHAR(500) NULL AFTER `excerpt_am`,
  ADD COLUMN `content_om` MEDIUMTEXT   NULL AFTER `content_am`;

-- ---------------------------------------------------------------------
--  system_settings - the four translated keys.
--
--  The settings screen renders whatever rows exist and writes only keys
--  that are already present, so a missing row is not an empty field on
--  the form - it is no field at all. These four therefore have to be
--  inserted, not just seeded for fresh installs.
--
--  INSERT IGNORE because the same four rows are also in seed.sql: a
--  database built from schema.sql + seed.sql and then brought up to date
--  with this migration would otherwise collide on the primary key.
--  Values are the shipped defaults; they are editable in the admin.
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `system_settings`
  (`setting_key`,`value`,`group_name`,`value_type`,`label`,`is_public`) VALUES
  ('clinic_name_om','Wiirtuu Yaalaa Aster','general','string','Clinic name (Afaan Oromo)',1),
  ('tagline_om','Tajaajila fayyaa olaanaa. Isin irratti kan xiyyeeffate.','general','string','Tagline (Afaan Oromo)',1),
  ('address_om','Kutaa Magaalaa Bolee, Finfinnee, Itoophiyaa','contact','string','Street address (Afaan Oromo)',1),
  ('operating_hours_om','Wiixata-Sanbata: 08:00 - 18:00 | Ariifachiisaa 24/7','contact','string','Operating hours (Afaan Oromo)',1);
