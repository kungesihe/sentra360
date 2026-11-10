# External integrations: what needs keys or paid services

The platform **never pretends** an external action happened. Without a configured provider, messages are stored with the status `not_configured`, and users are told plainly that nothing was sent.

| Integration | Needed for | Status in Phase 1 | What you need to provide |
|---|---|---|---|
| **SMS gateway** (e.g. Clickatell, BulkSMS, SMSPortal) | Mobile OTP verification; later alerts and notifications | ❌ Not configured. Codes are recorded, not sent. | Account and API key (paid per SMS). An adapter must be added in `App\Core\Notifier`. |
| **Email delivery** (SMTP, or a service such as Amazon SES, Mailgun or SendGrid) | Email verification, password reset | ❌ Not configured. Emails are recorded, not sent. | SMTP credentials or API key. An adapter must be added in `App\Core\Notifier`. |
| **PayFast / Peach Payments** | Client payments (Phase 3) | ⏳ Phase 3 | The company's merchant account: merchant ID/key, passphrase, ITN/webhook URL. |
| **Maps: Leaflet + OpenStreetMap tiles** | Incident and response maps (Phase 5) | ⏳ Phase 5 | Free for light use. OSM's tile policy forbids heavy commercial use, so production needs a paid tile provider (e.g. MapTiler, Stadia) with an API key. |
| **TLS certificate** | `https://` for the app address | Deployment | Let's Encrypt (free) or a purchased certificate. |
| **Web Push / browser notifications** | Security portal and homeowner alerts (Phase 4) | ⏳ Phase 4 | VAPID keys (free). Delivery depends on the browser being installed and permissions granted. |

## Until providers are connected

- **Development:** open `/dev/outbox` to read the undelivered codes and links. This page does not exist when `APP_ENV=production`.
- **Production without providers:** the Admin can **manually verify** a homeowner (Admin → Homeowners → homeowner → *Manually verify*) after confirming their details another way. The action is logged.

## Adding a provider

1. Set `MAIL_PROVIDER` / `SMS_PROVIDER` in `.env` (for example `smtp`, `clickatell`).
2. Implement the adapter in `App\Core\Notifier::dispatch()`. Set the status to `sent` only when the provider confirms acceptance, otherwise `failed` with the error.
3. Until the adapter exists, a configured-but-unimplemented provider is recorded as `failed`, never as `sent`.
