# Testing

## Automated tests

```bash
docker compose exec app php bin/test.php
```

- Uses a **separate database** (`sentra360_test`), rebuilt on every run, so real or demo data is never touched.
- Starts a private PHP web server on port 8099 and drives it with real HTTP requests: cookies, CSRF tokens, redirects and uploads.
- Run one file: `php bin/test.php Logins` (matches file names).

| File | Covers |
|---|---|
| `tests/AuthTest.php` | CSRF, password rules, consent, homeowner sign-up, OTP and email verification, honest "not sent" status, lockout, mobile sign-in, sign-out, password reset (incl. returning staff to the right login) |
| `tests/LoginsTest.php` | One sign-in: each role lands in its own area, old login links redirect, homeowners and security blocked from admin, admin blocked from security, staff not shown as homeowners, creating and disabling security logins, admin homeowner actions, removed multi-company pages |
| `tests/BrandingTest.php` | Branding on public and login pages, HTML escaping, real logo upload, bad colours and disguised files refused, database company-key constraint |

Tests for incidents, applications, quotations and payments will be added with those modules.

## Manual checklist

1. Sign in at `/login` as Admin. Under *Branding & profile*, upload a logo and change colours. Check the public site and the sign-in page.
2. Under *Admin & security logins*, create a Security company login. Sign in with it at `/login`: it opens the security area.
3. With that security login, open `/admin`: it must be refused (403).
4. Register as a homeowner at `/register`. Read the code and link at `/dev/outbox` and finish verification.
5. Sign in with a wrong password 5 times and confirm the lockout.
6. Use *Forgot password* and complete a reset from the outbox link.
7. As Admin, disable the security login and confirm it is signed out.
