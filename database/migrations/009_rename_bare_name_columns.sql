-- =====================================================================
--  009 - Rename bare `name` columns to a table-prefixed name
-- =====================================================================
--  Three tables carried a column literally called `name` (not
--  `first_name`/`last_name`-style, which stay untouched): services,
--  facilities, contact_inquiries. Renamed to <first-3-letters-of-table>_name
--  so every stored "name" is self-describing without a table join:
--
--    services.name           -> services.ser_name
--    facilities.name         -> facilities.fac_name
--    contact_inquiries.name  -> contact_inquiries.con_name
--
--  `name_am` (services/facilities) is a DIFFERENT column - not touched.
--  CHANGE COLUMN keeps the type/nullability exactly as it was; only the
--  identifier changes.
-- =====================================================================

ALTER TABLE `services`
  CHANGE COLUMN `name` `ser_name` VARCHAR(140) NOT NULL;

-- FULLTEXT KEY ft_services referenced the old column name explicitly and
-- must be dropped and recreated - MariaDB does not auto-update an index
-- definition when the column it names is renamed via CHANGE COLUMN.
ALTER TABLE `services`
  DROP KEY `ft_services`,
  ADD FULLTEXT KEY `ft_services` (`ser_name`, `description`);

ALTER TABLE `facilities`
  CHANGE COLUMN `name` `fac_name` VARCHAR(160) NOT NULL;

ALTER TABLE `contact_inquiries`
  CHANGE COLUMN `name` `con_name` VARCHAR(160) NOT NULL;
