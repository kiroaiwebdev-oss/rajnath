# Phase 1 Evidence — Setup and Planning (27–28 Oct 2026)

## Owner decisions recorded
- Swiss Ephemeris under **AGPL-3.0** → whole platform licensed AGPL-3.0 (see `LICENSE`, `docs/02-architecture.md`).
- Build in this repository (`kiroaiwebdev-oss/rajnath`), branch `platform-v1`.
- Web (public site, customer area, practitioner portal, admin) in **Laravel Blade**; mobile app in **Flutter**.
- Owner takes responsibility for: physical phone testing, live payment approval, VPS/domain,
  pandit/temple agreements, independent security review, translation review.

## Environment (actually used)
| Tool | Version | Result |
|---|---|---|
| OS | Amazon Linux 2023, x86_64, 8 vCPU, 30 GiB RAM, read-only root | — |
| PHP | 8.4.23 static CLI (gd, intl, pdo_mysql, sodium, bcmath, redis, swoole …) | `php -v` OK |
| Composer | 2.10.3 | OK |
| MariaDB | 11.4.13 (UTC, READ-COMMITTED, utf8mb4) | `SELECT VERSION()` OK |
| Laravel | 13.39.0 | `php artisan migrate:fresh` on MariaDB OK; `php artisan test` 2 passed |
| JDK | Temurin 17.0.21 | OK |
| Android SDK | platform 36, build-tools 36.0.0, NDK r28c (auto) | OK |
| Flutter | 3.47.8 stable | `flutter build apk --debug` OK (148.8 s); `flutter test` passed |
| Swiss Ephemeris | 2.10.03 compiled from source with gcc; sepl/semo/seas_18 files | `swetest` sidereal (Lahiri) positions for 2000-01-01 12:00 UT printed |

APK check (`aapt2 dump badging`): `package: name='com.astrorajnathjha.app' versionCode='1' versionName='1.0.0'`, `targetSdkVersion:'36'`.

## Sandbox limitations found (and workarounds)
- Root FS and `$HOME` are read-only → portable toolchain under `/projects/sandbox/.tools`, `HOME`/config redirected.
- Daemons do not survive between commands → `with-db` wrapper starts/stops MariaDB per command.
  Long-running services (LiveKit, Redis, queue workers) can only be exercised inside one command
  here; real soak/load tests must run on the owner's VPS.
- Docker/Podman not functional here → Compose files will be written and validated syntactically;
  container runs must happen on the VPS.
- No physical/virtual Android device available here (no KVM emulator tested yet).

## Deliverables of this phase
- `docs/01-feature-inventory.md`, `docs/02-architecture.md`, `docs/03-data-model.md`, `docs/04-traceability-matrix.md`
- `backend/` Laravel 13 skeleton configured for MariaDB; `mobile/` Flutter project `com.astrorajnathjha.app`
- `scripts/setup-dev-toolchain.sh` (reproducible toolchain)
- Old static site moved to `legacy-static-site/` for content reference only. It must **not** be
  deployed: its admin stores data in the browser and documents a default password.
