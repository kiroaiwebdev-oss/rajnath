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
- accounts with email/mobile OTP and staff MFA
- roles and audit log
- catalogue and public website/PWA
- Email/SMS provider setup
- 26 calculators and daily Panchang
- Razorpay checkout, wallet, ledger, refunds and reconciliation
- gemstone store (stock, shipping, dispatch) and paid palmistry report from palm photos
- Android app (core screens) with a release-signed APK and AAB
- internal security review (`docs/security/security-review.md`)

**Not yet built:**
- consultation booking and audio/video calling (LiveKit)
- report generation and PDFs
- puja/chadhava fulfilment and suppliers
- horoscope publishing
- support tickets, push notifications, AI assistant
- practitioner portal and payouts
- data export and deletion
- Hindi and other languages

See `docs/04-traceability-matrix.md`.

**Owner acceptance pending:** VPS deployment, live providers, physical phones, independent security review.

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
