# Setting the app up for another security company

The app serves one company. For a second company, run a **separate copy** with its **own database**. The companies never share data.

## Steps

1. **Copy the project folder**, e.g. `Sentra360` → `Sentra360-SecureGuard`. Do not copy `public/uploads/` (the first company's logo) or `.env`.
2. **Create the new `.env`** from `.env.example` and change:
   - `APP_KEY`: a new long random string
   - `APP_URL`: the new company's web address, e.g. `https://app.secureguard.co.za`
   - `COMPANY_NAME`, `COMPANY_PHONE`, `COMPANY_EMAIL`, `COMPANY_EMERGENCY_PHONE`, `COMPANY_PSIRA`, colours
   - `ADMIN_EMAIL`, `ADMIN_MOBILE`, `ADMIN_PASSWORD`: the company's first admin login
   - `DB_NAME`: a new database name (and new `DB_PASS`)
3. **Docker only:** in `docker-compose.yml` change `name:`, both `container_name:` values, and the ports (`8360`, `3360`) so both copies can run at the same time.
4. **Install:** `php bin/install.php` (or `docker compose exec app php bin/install.php`).
5. **Sign in at `/login`** with the Admin account and, under *Branding & profile*, upload the logo and set the colours, welcome text, services, emergency number, privacy policy and terms.
6. **Create the security company logins** under *Admin & security logins*.

Steps 1–4 take about 15 minutes. Everything the public sees (name, logo, colours, texts) is changed in step 5 without touching code.

## Keeping copies up to date

When the app gets new features, copy the new code into each company's folder **without** overwriting `.env` or `public/uploads/`, then run `php bin/install.php` in each. It applies only new database changes and leaves existing data alone.
