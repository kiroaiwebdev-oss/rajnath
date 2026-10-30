# Astro Rajnath Jha Platform

Astrology and spiritual-services platform for **astrorajnathjha.com**: Laravel 13 + Blade website/admin, REST API v1, Flutter Android app, Swiss Ephemeris engine.

| Folder | Contents |
|---|---|
| `backend/` | Laravel 13 (PHP 8.4): public site, customer account, admin (Blade), API `/api/v1`, queues, scheduler |
| `engine/` | `arj-ephem` — Swiss Ephemeris (AGPL-3.0) JSON CLI; `engine/build.sh` |
| `mobile/` | Flutter Android app `com.astrorajnathjha.app` |
| `deploy/` | Docker Compose (Caddy TLS, PHP-FPM, MariaDB, Redis, queue, scheduler), backup/restore/deploy scripts |
| `docs/` | Inventory, architecture, data model, **traceability matrix (live status)**, methods, ops guides, evidence |

## Status (honest)
**Implemented and tested:**
- accounts with email/mobile OTP and staff MFA; roles, audit log, operational health screen
- catalogue and public website/PWA in English and Hindi (more languages switch on per `SITE_LOCALES`)
- Email/SMS provider setup
- 26 calculators; Panchang with Choghadiya and Hora; muhurat finder; festival calendars (6 religions); Grah Gochar for 9 planets
- Rashifal publishing: daily to yearly, love/career/finance/health/education, nakshatra, Chinese, numerology, tarot
- Razorpay checkout, wallet, ledger, refunds, reconciliation, coupons, GST tax invoices and credit notes
- consultation booking with in-app calls (LiveKit), Zoom and Google Meet links
- reports (expert-written) with server-printed PDFs; puja/chadhava fulfilment with suppliers
- gemstone store with Shiprocket courier booking and tracking; paid palmistry report from palm photos
- support tickets, push notifications, AI assistant
- practitioner portal, astrologer applications, commission and payouts (RazorpayX or manual UTR)
- data export and deletion with a staff privacy queue and legal hold
- SEO, AEO and GEO: sitemap with hreflang, robots.txt, llms.txt, canonical/Open Graph/geo tags, schema.org data on every public page
- one-command VPS install (`deploy/install.sh`); all provider keys are entered in the admin
- Android app (1.4.0 source) with the same features as the website, and an internal security review (`docs/security/security-review.md`)

**Drafts, need review:** text for Bengali, Gujarati, Kannada, Malayalam, Marathi, Punjabi, Tamil and Telugu (`backend/lang/`). They stay off until a native speaker checks them.

See `docs/04-traceability-matrix.md`.

**Owner acceptance pending:** VPS deployment, live providers (Razorpay live, RazorpayX, Shiprocket, SMS, Firebase, LiveKit), GSTIN and tax rates checked by an accountant, review of the Hindi text and muhurat rules, a signed 1.4.0 APK on physical phones, independent security review.

## Quick start (development)
```
scripts/setup-dev-toolchain.sh && . ../.tools/env.sh
engine/build.sh
cd backend && composer install && npm ci && npm run build && cp .env.example .env && php artisan key:generate
php artisan migrate --seed && php artisan test
```
Production: `docs/ops/vps-deployment.md`. Release signing: `docs/release/signing.md`.

## Licence
AGPL-3.0-or-later (Swiss Ephemeris used under its AGPL option). Secrets, `.env`, keystores and data are not part of the source.
