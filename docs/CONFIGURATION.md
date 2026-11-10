# Configuration

All settings live in `.env`.

| Setting | Meaning |
|---|---|
| `APP_NAME` | Product name ("Powered by …" in the footer). |
| `APP_ENV` | `development` turns on error details and the Developer Outbox. **Use `production` live.** |
| `APP_URL` | The address people open, e.g. `https://app.yourcompany.co.za`. Used in email links. |
| `APP_KEY` | Secret used to protect verification codes and links. Long and random. |
| `COMPANY_*` | Name, phone, email, emergency number, PSIRA number and colours. **Used only on first install**; after that, change them in Admin → *Branding & profile*. |
| `ADMIN_EMAIL`, `ADMIN_MOBILE`, `ADMIN_PASSWORD` | First Admin login, created on first install. |
| `DB_*` | Database connection. |
| `MAIL_PROVIDER`, `MAIL_FROM` | Email delivery. Empty means **not configured**; see [INTEGRATIONS.md](INTEGRATIONS.md). |
| `SMS_PROVIDER` | SMS/OTP delivery. Empty means **not configured**. |
| `REQUIRE_VERIFICATION` | `0` (default): homeowners are active immediately. `1`: they must confirm mobile (SMS code) and email first. Only switch on once SMS and email providers work. |
| `PAYFAST_MERCHANT_ID`, `PAYFAST_MERCHANT_KEY`, `PAYFAST_PASSPHRASE`, `PAYFAST_SANDBOX` | Online card payments via PayFast. Leave empty to use EFT + manual recording. `PAYFAST_SANDBOX=1` (default) uses PayFast's test site. |

## One sign-in, three kinds of account

| Login | Role code | Can |
|---|---|---|
| Homeowner | `homeowner` | Own account: profile, verification, password, devices. Later: incidents, applications, quotations, panic button. |
| Admin | `admin` | Everything: homeowners, admin and security logins, branding, audit log. |
| Security company | `security_staff` | Look up homeowners (read-only). Later: incident feed, panic alerts, assignments, map. |

Everyone signs in at `/login`; the role decides the landing area. Portals are defined in `App\Core\Auth::PORTALS`.

## Security settings in code

| Setting | Where | Default |
|---|---|---|
| Failed sign-ins before lockout | `Auth::MAX_FAILS` | 5 |
| Lockout length | `Auth::LOCK_MINUTES` | 15 min |
| Idle session timeout | `Auth::IDLE_SECONDS` | 30 min |
| Absolute session lifetime | `Auth::ABSOLUTE_SECONDS` | 12 h |
| OTP validity / attempts | `VerificationService` | 10 min / 5 |
| Password reset link validity | `VerificationService::RESET_TTL` | 1 h |
| Logo upload | `AdminController::brandingSave` | 1 MB, PNG/JPG/WEBP |
