# Evidence — Phase 3 (customer accounts) + Phase 4 (catalogue & website) · 28 Oct 2026

## Built
**Accounts (web + API):**
- Passwordless sign-up and sign-in with email OTP or mobile OTP (SMTP / Twilio / Fast2SMS from Phase 3a).
- Codes: 6 digits, 10 minutes, 5 wrong attempts then locked, single use, stored only as HMAC.
- Limits: 30 s resend cooldown, 5 codes per address per hour, 30 per IP per hour.
- No account enumeration. Terms consent is recorded for new accounts. Staff and suspended accounts cannot use customer sign-in.
- Recovery: add or verify a second channel (email or mobile, OTP to the new address).
- Profile, language and notification preferences.
- Devices: browser sessions and app tokens, sign out one or all others.
- Birth profiles (up to 20). GeoNames place search (34,191 cities, India first) with an accessible combobox. Historical time-zone conversion to UTC: India +06:30 war time, DST gap and overlap flagged and customer-selectable.
- API v1 for Flutter (`docs/api/v1.md`): OTP auth, 90-day per-device Bearer tokens, `/me`, devices, birth profiles, catalogue, places.

**Catalogue & website:**
- Service families, offerings, translations, variants. Prices are integer paise, "Price on request" until set.
- Safe seed: the 10 services Acharya Ji provides today are **active**. 76 inventory items (reports, bundles, 44 pujas, 7 chadhava, ask-a-question) are **draft/hidden** until their workflows exist. Chadhava cannot be activated without an authorised supplier.
- Public site: home, services with search/category/mode filters, service detail (includes, audience, disclaimer, options and prices, request form), about (full biography, education, positions, awards, research, books, media), contact and timings, FAQ, privacy, terms, offline page.
- PWA: manifest, icons, service worker with offline fallback. Account, admin and API responses are never cached.
- Owner photos from the repo are used per service and page (WebP + JPEG, lazy loading, fixed dimensions).
- Request/enquiry flow: honeypot, consent and rate limit. Admin follow-up with status, internal note, call/WhatsApp buttons and CSV export (formula-injection safe). Staff viewing an enquiry is audited.
- Admin: catalogue editor (text, image, featured, order, status rules), variants and prices (`prices.manage` + step-up), customers (masked list, suspend/reactivate with reason, revokes all sessions), FAQs (publisher role).

## Verified (actual runs, MariaDB 11.4.13)
- `php artisan test`: **88 passed, 573 assertions** (`phase-3-4/test-run.txt`). Covers:
  - OTP lifecycle, lockout, rate limits and purpose isolation
  - SMS OTP through Fast2SMS (faked HTTP)
  - historical time zones and DST
  - cross-user isolation on web and API (404)
  - token expiry, revocation and suspension
  - draft hiding, price permissions and step-up, supplier gate
  - enquiry validation, CSV injection, Indian money formatting
- Browser (Chromium 156), real OTP sign-up with the code read from the mail log, then birth profile creation by keyboard in the place combobox, then a request submission:
  - **32 page checks at desktop and phone: 0 axe WCAG A/AA violations, 0 horizontal overflow, 0 console errors.**
  - Fixed during the run: inline links that were distinguishable only by colour.
- `composer audit`: no advisories · `npm audit`: 0 vulnerabilities.

## Not done / not verified
- No real email or SMS sent to a real inbox or phone (needs the owner's provider credentials).
- Hindi UI and translated service text are not done; the language preference is stored only.
- Self-service data export and account deletion: Phase 10. The account page says so and offers email handling.
- Online booking, payment, wallet and reports: Phases 5–8. Service pages say "Online booking and payment are being set up".
- Flutter screens using this API: Phase 11.
