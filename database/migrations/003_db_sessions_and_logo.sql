-- Keep sign-ins and the company logo in the database, so they survive
-- restarts on hosts with temporary disks (e.g. Render's free plan).
-- Written to work on MySQL, MariaDB and TiDB (one column per ALTER).

CREATE TABLE IF NOT EXISTS php_sessions (
  id          VARCHAR(128) NOT NULL PRIMARY KEY,
  data        MEDIUMBLOB   NOT NULL,
  last_access INT UNSIGNED NOT NULL,
  KEY ix_php_sessions_access (last_access)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE tenant_settings ADD COLUMN logo_data MEDIUMBLOB NULL AFTER logo_path;

ALTER TABLE tenant_settings ADD COLUMN logo_mime VARCHAR(30) NULL AFTER logo_data;
