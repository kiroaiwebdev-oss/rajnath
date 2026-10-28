# Astro Rajnath Jha Platform

Astrology and spiritual-services platform for **astrorajnathjha.com**.

| Folder | Contents |
|---|---|
| `backend/` | Laravel 13 (PHP 8.4): public website, customer area, practitioner portal and admin in Blade; REST API `/api/v1`; queues, scheduler |
| `mobile/` | Flutter Android app `com.astrorajnathjha.app` |
| `engine/` | Swiss Ephemeris wrapper (planned) |
| `deploy/` | VPS / Docker Compose tooling (planned) |
| `docs/` | Inventory, architecture, data model, traceability, evidence |
| `legacy-static-site/` | Previous static site, kept only as content reference. Do not deploy. |

## Status
Phases 1–2 of 12 complete (setup, planning, foundation). See `docs/04-traceability-matrix.md` for the live status.

## Development
```
scripts/setup-dev-toolchain.sh      # portable PHP, MariaDB, JDK, Flutter, Android SDK, Swiss Ephemeris
. ../.tools/env.sh
cd backend && composer install && cp .env.example .env && php artisan key:generate
```

## Licence
AGPL-3.0-or-later (required because Swiss Ephemeris is used under its AGPL licence).
Swiss Ephemeris © Astrodienst AG. Secrets, environment files, keystores and data are not part
of the published source.
