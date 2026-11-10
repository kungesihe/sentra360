-- SENTRA 360 — switch to single-company mode with three logins.
-- Removes the multi-company SaaS parts (plans, subscriptions, platform owner,
-- sales leads, support grants, permission matrix) and maps the old roles onto
-- the three account types: homeowner, admin, security_staff.

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS support_access_grants;
DROP TABLE IF EXISTS subscriptions;
DROP TABLE IF EXISTS subscription_plans;
DROP TABLE IF EXISTS platform_users;
DROP TABLE IF EXISTS demo_requests;
DROP TABLE IF EXISTS contact_messages;
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS permissions;
SET FOREIGN_KEY_CHECKS = 1;

DELETE FROM user_sessions WHERE user_type = 'platform';
-- (user_sessions.user_type keeps its old ENUM values; shrinking an ENUM is not
-- supported by every MySQL-compatible database, e.g. TiDB.)

-- Roles: community_member -> homeowner, company_admin -> admin,
-- control_room_operator + response_personnel -> security_staff
UPDATE roles SET code = 'homeowner', name = 'Homeowner', is_staff = 0, description = 'Member of the public / client' WHERE code = 'community_member';
UPDATE roles SET code = 'admin', name = 'Admin', is_staff = 1, description = 'Company office: full control' WHERE code = 'company_admin';
UPDATE roles SET code = 'security_staff', name = 'Security company', is_staff = 1, description = 'Control room and response officers' WHERE code = 'control_room_operator';
UPDATE users SET role_id = (SELECT id FROM roles WHERE code = 'security_staff')
 WHERE role_id = (SELECT id FROM roles WHERE code = 'response_personnel')
   AND EXISTS (SELECT 1 FROM roles WHERE code = 'security_staff');
DELETE FROM roles WHERE code = 'response_personnel';
