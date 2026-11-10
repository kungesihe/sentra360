# SENTRA 360

Security management, community safety and emergency response app for **one** private security company, with one sign-in page (`/login`) for three kinds of account:

| Login | Who | Signs in at | Lands on |
|---|---|---|---|
| **Homeowner** | Residents and clients. They sign themselves up. | `/login` | `/account` |
| **Admin** | The company's office. Full control. | `/login` | `/admin` |
| **Security company** | Control room and response officers. Logins are created by the Admin. | `/login` | `/security` |

Everyone signs in at `/login`; the account's role decides where it lands, and each account only opens its own area. The old `/admin/login` and `/security/login` links redirect to `/login`.

The panic button is open to every homeowner with a complete profile (name, mobile, email, a registered home address and at least one emergency contact), clients and community members alike. Alerts from community members show up in the control room labelled as such, and the Admin's **Leads** page lists them so the company can offer them a protection plan.

To serve a second company later, copy the app, give the copy its own database and change the name and logo. See [docs/NEW_COMPANY.md](docs/NEW_COMPANY.md).

## What works now (Phases 1–6)

| Area | Homeowner | Admin | Security company |
|---|---|---|---|
| **Incidents**: 22 crime/emergency categories, photos/video/documents, GPS, witnesses, injuries, reference numbers, honest status (submitted → received → assigned → in progress → resolved) | Report & track | Manage, search/filter, assign, notes | Live feed, respond, update, photos |
| **Panic button**: clients only; hold 3 s (or confirm box), GPS + live location updates, silent/audible, extra info, emergency contacts; status shows submitted / seen by control room / acknowledged / dispatched / resolved | ✅ | Sees & manages | Live control room with map and alarm sound, acknowledge, dispatch |
| **Residential & vehicle protection**: register multiple properties/vehicles, apply, assessment, quotation, acceptance, payment, activation → client | ✅ | Full workflow | — |
| **Quotations**: line items, VAT, monthly/once-off, accept/decline, print/PDF | Accept/decline | Create, send, record payments | — |
| **Services marketplace & bookings**: listings with prices or quote-on-request, request date/time/location/duration → quote → pay → confirmed | ✅ | Manage services & requests | — |
| **Payments**: EFT instructions + manual recording; PayFast framework (needs keys) | History | Record, export | — |
| **Community safety alerts**, **patrol log**, **notifications**, **reports** (incidents, response times, conversion, revenue, services) with **Excel (CSV) and PDF export**, **audit log** | ✅ | ✅ | ✅ |
| Verification of mobile/email | Switched **off** for now (`REQUIRE_VERIFICATION=0`) | | |
| Automated tests | 33 passing (incl. a smoke test of every page) | | |

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
