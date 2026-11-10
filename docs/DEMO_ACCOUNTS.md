# Demonstration accounts

Created by `php bin/install.php --demo`. **Demonstration environments only.** The installer refuses to load them when `APP_ENV=production`. While demo data is loaded, the site shows a yellow "Demonstration company" ribbon.

All demo accounts use the password **`Demo!2026pass`**.

| Login type | Sign in at | Email |
|---|---|---|
| Admin | http://localhost:8360/login | `admin@demo.test` |
| Security company (control room) | http://localhost:8360/login | `control@demo.test` |
| Security company (officer) | http://localhost:8360/login | `officer@demo.test` |
| Homeowner (verified) | http://localhost:8360/login | `lerato@demo.test` |
| Homeowner (verified) | http://localhost:8360/login | `sipho@demo.test` |
| Homeowner (awaiting verification) | http://localhost:8360/login | `anika@demo.test` |

Without `--demo`, the installer creates one Admin login from `ADMIN_EMAIL` / `ADMIN_PASSWORD` in `.env` (defaults `admin@company.local` / `ChangeMe!Admin360`). Change that password after the first sign-in.
