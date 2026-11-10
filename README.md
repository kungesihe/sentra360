# SENTRA 360

Security management, community safety and emergency response app for **one** private security company, with three logins:

| Login | Who | Signs in at | Lands on |
|---|---|---|---|
| **Homeowner** | Residents and clients. They sign themselves up. | `/login` | `/account` |
| **Admin** | The company's office. Full control. | `/admin/login` | `/admin` |
| **Security company** | Control room and response officers. Logins are created by the Admin. | `/security/login` | `/security` |

Each login only works on its own page and only opens its own area.

To serve a second company later, copy the app, give the copy its own database and change the name and logo. See [docs/NEW_COMPANY.md](docs/NEW_COMPANY.md).

## What works now (Phase 1)

| Area | Status |
|---|---|
| Branded public site: welcome, services, emergency number, contact, privacy, terms | ✅ |
| Homeowner sign-up with mobile code and email verification, sign-in by email or mobile, lockout, password reset, "sign out everywhere" | ✅ (SMS and email delivery not connected yet; see below) |
| Admin: dashboard, homeowners (verify, suspend, mark as client), create and disable admin and security logins, branding (logo, colours, texts), audit log | ✅ |
| Security company: dashboard, homeowner lookup, own password | ✅ |
| POPIA: consent records, masked contact details in lists, logged access to records | ✅ |
| Automated tests | ✅ 22 passing |
| Incidents, property and vehicle applications, quotations, payments, panic button, live control room | ⏳ Phases 2–5 |

## Quick start

Requires Docker Desktop.

```bash
cp .env.example .env            # set APP_KEY, COMPANY_NAME, ADMIN_EMAIL, ADMIN_PASSWORD
docker compose up -d --build
docker compose exec app php bin/install.php          # or --demo for sample accounts
```

Open http://localhost:8360. Demo logins are in [docs/DEMO_ACCOUNTS.md](docs/DEMO_ACCOUNTS.md).

## Documentation

- [docs/INSTALL.md](docs/INSTALL.md): installation (Docker or normal PHP hosting)
- [docs/CONFIGURATION.md](docs/CONFIGURATION.md): settings
- [docs/NEW_COMPANY.md](docs/NEW_COMPANY.md): copying the app for another company
- [docs/INTEGRATIONS.md](docs/INTEGRATIONS.md): **what needs API keys or paid services**
- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md): code layout and security
- [docs/TESTING.md](docs/TESTING.md): automated and manual tests

## Tech

PHP 8.2, MariaDB 11 (MySQL-compatible), Bootstrap 5.3, plain JavaScript. No framework, no Composer dependencies.
