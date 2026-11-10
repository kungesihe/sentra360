# Architecture

## Layout

```
public/            web root: index.php (front controller), assets, favicon, uploads (no script execution)
app/
  bootstrap.php    env loading, autoloader, error handling
  helpers.php      e() escaping, app_url(), money, mobile normalisation, hashing
  routes.php       every URL, grouped by login type
  Core/            DB, Router, Session, Csrf, Auth (three portals), TenantContext (the company), RateLimiter, Audit, Notifier, Upload, Validator
  Services/        UserService, VerificationService, Installer, DemoSeeder
  Controllers/     Tenant/ PublicController, AuthController, AccountController (homeowner), AdminController, SecurityController; DevController
  Views/           layouts/ (tenant = public + homeowner, portal = admin + security, bare), tenant/, admin/, security/, shared/
database/migrations/  numbered SQL files, applied once each by bin/install.php
bin/               install.php, test.php
tests/             end-to-end tests against a throwaway database
```

## Request flow

1. `public/index.php` sends security headers, starts the session and loads the company record.
2. The router checks CSRF on every POST, then runs `Auth::requirePortal('homeowner'|'admin'|'security')` for protected pages.
3. The controller runs and renders a view inside a layout.

## One company, ready to copy

The company's record is the single row in `tenants`, with branding in `tenant_settings`. Every company-owned row still carries `tenant_id` with composite foreign keys, which is a cheap extra safety net. For another company, copy the app with its own database (see NEW_COMPANY.md).

## Logins and access

`Auth::PORTALS` maps each login type to its role, login page and home page. Sign-in checks the account's role against the login page used. A homeowner can't sign in through the admin page, and so on. `requirePortal()` blocks every page of another portal with 403, and the denial is logged.

## Security controls

| Control | Implementation |
|---|---|
| Password hashing | `password_hash()` / `password_verify()`, rehash on login when needed |
| SQL injection | PDO prepared statements only; identifiers checked against a whitelist pattern |
| XSS | All output through `e()`; strict Content-Security-Policy (no inline scripts); colours validated before use in CSS |
| CSRF | Per-session token checked on every POST |
| Sessions | HttpOnly, SameSite=Lax, Secure on HTTPS, ID regenerated at login, server-side revocable record, idle and absolute timeouts |
| Lockout and rate limits | Per-account lockout; per-IP limits on sign-in, registration, password reset, OTP |
| OTP and tokens | Random values; only HMAC hashes stored; single use; expire; limited attempts |
| Uploads | Size limit, content sniffing, image check, random names, no SVG uploads, no script execution in `uploads/` |
| Audit | Sign-ins, wrong-portal attempts, lockouts, access denials, homeowner record views, status changes, staff changes, branding |
| Enumeration | Generic login error and identical password-reset response; timing-equalised failed login |
| Headers | CSP, X-Frame-Options DENY, nosniff, Referrer-Policy, Permissions-Policy, HSTS on HTTPS |
