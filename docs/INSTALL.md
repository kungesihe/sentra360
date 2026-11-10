# Installation

## Option A: Docker (development and small deployments)

1. Install Docker Desktop and start it (`docker desktop start`).
2. In the project folder:
   ```bash
   cp .env.example .env
   ```
   Edit `.env`: set `APP_KEY` to a long random string, and set `COMPANY_NAME`, `ADMIN_EMAIL` and `ADMIN_PASSWORD`.
3. Start the containers:
   ```bash
   docker compose up -d --build
   ```
   - `sentra360-app`: PHP 8.2 + Apache on port **8360**
   - `sentra360-db`: MariaDB 11.4 on port **3360** (for database tools; user `sentra`)
4. Create the tables, the company and the first Admin login:
   ```bash
   docker compose exec app php bin/install.php
   ```
   - `--demo` loads sample logins instead of the `.env` admin (refused when `APP_ENV=production`).
   - `--fresh` wipes the database first (development only).
5. Open http://localhost:8360 and sign in at `/admin/login`.

## Option B: Standard PHP hosting (production)

Requirements: PHP 8.2+ with `pdo_mysql`, `fileinfo`, `mbstring`; MySQL 8 or MariaDB 10.6+; Apache with `mod_rewrite` and `mod_headers` (or nginx); HTTPS.

1. Upload the project. Point the web root at **`public/`** only. `app/`, `bin/`, `database/` and `.env` must not be web-accessible.
2. Create a database and user. Copy `.env.example` to `.env`, fill it in, and set `APP_ENV=production` and `APP_URL=https://your-address`.
3. Run `php bin/install.php` from the command line.
4. Make `public/uploads/` and `storage/logs/` writable by the web server.
5. Schedule daily database backups.

nginx users: route everything that isn't a real file to `public/index.php`, and deny PHP execution in `public/uploads/`.

## Backups

```bash
docker compose exec db sh -c 'mariadb-dump -usentra -p"$MARIADB_PASSWORD" sentra360' > backup.sql
```
Keep at least 30 days of daily backups off-site and encrypted, and test restoring them. Back up `public/uploads/` (the logo) as well.

## Upgrading

Copy in the new code (keep `.env` and `public/uploads/`), then run `php bin/install.php`. It applies only new database changes.
