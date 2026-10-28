# Evidence — Email (SMTP) and SMS OTP providers (28 Oct 2026)

## Implemented
- **Admin → Email & SMS** (`/admin/providers`): owner-only (`providers.manage`), step-up for save and test, rate-limited test buttons (5/min).
- **SMTP**: host, port (25/465/587/2525), TLS or SSL, username, password (encrypted, masked, blank = keep, explicit remove), from address and name. Applied to Laravel's mailer at runtime; falls back to `.env` when unset. TLS mode sets `require_tls`. SSRF guard on the host (private, loopback, link-local, CGNAT and reserved ranges blocked; DNS-rebinding style mixed answers blocked).
- **SMS**: choice of **Twilio** (Messages API, From number or Messaging Service SID, OTP text template) or **Fast2SMS** (bulkV2, DLT route with sender ID + message ID + ordered variables, or OTP route). API key sent in the header, never in the URL. Non-Indian numbers optionally routed to Twilio when Fast2SMS is primary. Both providers' settings are kept when switching.
- **Delivery log** (`provider_deliveries`): masked recipient, HMAC hash for lookups, provider ID, status, duration and error. Shown on the admin page.
- Owner guide: `docs/ops/email-sms-setup.md`.

## Tests (actual run, MariaDB 11.4.13): 50 passed, 279 assertions (`messaging/test-run.txt`)
- Twilio and Fast2SMS: exact request URL, method, parameters and auth header checked with HTTP fakes; provider error codes, network failure and incomplete configuration all reported without exceptions.
- **Real SMTP conversation** with a local SMTP server process: AUTH with the admin-saved credentials, MAIL FROM, RCPT TO, subject and body verified.
- TLS mode refuses a server without TLS and never sends the password.
- Admin: permission isolation (5 non-owner roles get 403), step-up enforcement, encryption at rest, masking, blank-keeps-secret, SSRF host rejection, validation, switching providers.

## Browser checks (Chromium headless 156)
17 page checks at desktop and phone sizes: 0 axe-core WCAG A/AA violations, 0 horizontal overflow, 0 console errors.
Provider switching was checked in the browser: only the chosen provider's fields are shown.

## Not verified (needs owner's real accounts)
No real email or SMS was sent to a real inbox or phone; tests used fakes and a local SMTP server.
To verify: enter real credentials in admin, use the two test buttons, and check the inbox and phone.
Delivery-report webhooks (handset delivery confirmation) are not built yet.
