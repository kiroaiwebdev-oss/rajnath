# Architecture

## Overview

One backend, one database, three client surfaces.

```
                 ┌──────────────── VPS (Docker Compose) ────────────────┐
 Browser ──TLS──▶│ Caddy/Nginx ─▶ PHP-FPM (Laravel 13)                  │
 (Blade web,     │                 ├─ Web: public site + customer        │
  PWA, admin,    │                 │       dashboard (Blade, sessions)   │
  practitioner)  │                 ├─ Admin + practitioner portal (Blade)│
                 │                 └─ API /api/v1 (Sanctum tokens)       │
 Flutter app ───▶│                                                       │
                 │ Queue workers (Horizon/Redis) · Scheduler             │
                 │ MariaDB 11.4 · Redis · private storage (local/S3)     │
                 │ arj-ephem (Swiss Ephemeris CLI) · PDF renderer        │
                 │ LiveKit SFU + TURN (coturn/LiveKit TURN)              │
                 └───────────────────────────────────────────────────────┘
 External: Razorpay · SMTP · SMS OTP (DLT) · FCM · AI provider · courier
```

## Stack decisions

| Area | Decision | Why |
|---|---|---|
| Backend | Laravel 13, PHP 8.4 | Owner preference; mature queues, auth, migrations |
| Web UI | **Blade** + Alpine.js + Tailwind CSS (compiled with Vite) | Owner requirement: website, customer area, practitioner portal and admin all in Blade |
| Mobile | **Flutter** (Android first), Riverpod, Dio, go_router, flutter_secure_storage | Owner requirement; genuine native app, not a WebView |
| API | REST `/api/v1`, JSON, OpenAPI 3.1 spec, Sanctum personal access tokens per device | Versioned, shared by the app and any future client |
| Database | MariaDB 11.4 LTS, InnoDB, utf8mb4, UTC session time zone, READ COMMITTED | Brief preference; row locks for wallet/slots |
| Cache/queue/locks | Redis 7; Laravel Horizon | Retries, failed-job table as dead-letter, atomic locks |
| Ephemeris | Swiss Ephemeris 2.10.03, **AGPL-3.0 licence** (owner decision 27 Oct 2026), compiled into a small C CLI `arj-ephem` returning JSON | PHP FFI not assumed; process boundary is simple to test and sandbox |
| PDF | Headless Chromium (Gotenberg container) rendering Blade templates; Noto fonts for 10 Indic scripts | Correct Indic shaping (conjuncts) which PHP PDF libraries handle poorly |
| Media | Self-hosted LiveKit server + TURN; tokens minted server-side; webhooks verified | Brief requirement |
| Payments | Razorpay (Orders, Payments, Refunds, webhooks); RazorpayX or manual for payouts | Brief requirement |
| Push | Firebase Cloud Messaging (HTTP v1) | Android standard |
| Permissions | spatie/laravel-permission + policies, default-deny | Least privilege |
| MFA | TOTP (RFC 6238) for all staff; step-up re-auth window 10 min | ASVS L2 |

## Licence consequence (AGPL-3.0)

Because the platform links Swiss Ephemeris under AGPL-3.0, the **whole platform source is
licensed AGPL-3.0**. Users interacting with it over the network must be offered the
corresponding source. The site footer and the app's About screen carry a "Source code" link to
a public repository/tarball of the exact deployed version. Secrets, `.env`, keystores and data
are **not** part of the source and are never published. Switching to the Swiss Ephemeris
professional licence later is possible without code changes.

## Code organisation (backend)

```
backend/app/
  Domain/<Module>/        Models, Actions (single-purpose services), States, Events, Policies
  Http/Controllers/Web/   Blade controllers (public, customer)
  Http/Controllers/Admin/ Admin + staff Blade controllers
  Http/Controllers/Practitioner/
  Http/Controllers/Api/V1/
  Support/                Money, Clock, Idempotency, Audit, SecretVault, SafeHttp (SSRF guard)
```

Modules: Identity, Catalogue, Booking, Consultation, Media, Ledger, Payments, Payouts, Reports,
Astro (engine), Panchang, Content, Puja, Support, Notifications, Privacy, Assistant, Settings, Audit.

## Cross-cutting rules

- **Money**: `BIGINT` minor units (paise) + ISO currency; `Money` value object; no floats anywhere in finance.
- **Ledger**: double-entry; every financial event writes balanced entries in one DB transaction;
  balances are derived and cached with `SELECT … FOR UPDATE` on the account row.
- **Idempotency**: `Idempotency-Key` header on mutating API calls; unique keys on provider
  event IDs (Razorpay `event.id`, LiveKit webhook `id`).
- **Snapshots**: orders copy price, tax, policy versions and catalogue text at purchase.
- **State machines**: explicit transition tables per aggregate (booking, session, payment, refund,
  payout, report job, puja order, ticket, privacy request); invalid transitions throw and are logged.
- **Time**: all timestamps UTC in DB; birth data stored as local wall time + IANA zone + resolved
  UTC instant + resolution flag (`exact`, `ambiguous_earlier`, `ambiguous_later`, `gap_shifted`).
  Historical offsets from the IANA tz database bundled with PHP/ICU.
- **Policies**: commission, refund, cancellation, no-show, retention are versioned rows with
  `effective_from`, `approved_by`, `approved_at`; only approved versions apply.
- **Jobs**: retries with backoff, `failed_jobs` as dead-letter, admin view + replay.
- **Logging**: JSON logs, PII redaction processor (phone, email, birth data, tokens).
- **Health**: `/up` (liveness), `/ready` (DB, Redis, storage, queue lag, ephemeris).
- **Secrets**: provider credentials stored encrypted (Laravel encrypter, key from env), masked
  in UI, change requires step-up; outbound URLs pass an SSRF guard (deny private ranges).

## Development environment (this sandbox)

Portable toolchain in `/projects/sandbox/.tools` (outside the repo): PHP 8.4.23 static build,
Composer 2.10, MariaDB 11.4.13, JDK 17, Flutter 3.47.8, Android SDK 36, Swiss Ephemeris 2.10.03.
The sandbox does not keep daemons alive between commands, so MariaDB is started per command
via `with-db`. Production uses Docker Compose (see `deploy/`).
