-- =====================================================================
--  010 - Brand & Theme permission
-- =====================================================================
--  Adds the brand.manage permission behind the new "Brand & Theme" admin
--  screen (CompanyBrand.json editor), granted to super_admin only - the
--  same pattern migration 008 used for roles.manage / wards.manage.
-- =====================================================================

INSERT INTO `permissions` (`slug`) VALUES ('brand.manage');

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p
WHERE r.slug = 'super_admin' AND p.slug = 'brand.manage';
