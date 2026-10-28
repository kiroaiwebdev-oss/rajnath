# Phase 2 Evidence — Foundation (28 Oct 2026)

## Implemented
| Area | What exists | Where |
|---|---|---|
| Roles & permissions | 9 roles (owner, administrator, finance, support, content author/reviewer/publisher, fulfilment, practitioner), 38 permissions, default-deny; `arj:rbac-sync` (idempotent) | `config/rbac.php`, `app/Console/Commands/RbacSync.php` |
| Owner bootstrap | `arj:bootstrap-owner` prints a one-time 30-min link; no default credentials; refuses if an owner exists | `BootstrapOwner.php`, `docs/ops/owner-bootstrap.md` |
| Staff invitations | Hashed single-use tokens, 48 h expiry, revoke, role whitelist, only owners can grant owner | `Domain/Identity/StaffInvitations.php` |
| Staff MFA | TOTP mandatory for every staff account, secret encrypted at rest, code replay blocked | `Domain/Identity/Mfa.php` |
| Step-up | Password + TOTP re-check within 10 min for team, roles, settings changes | `RequireStepUp.php` |
| Account controls | Suspend → sessions and API tokens revoked; last-owner protection; ULID route keys (no sequential IDs) | `Admin/TeamController.php` |
| Audit trail | Append-only, SHA-256 hash-chained, row-locked appends with deadlock retry, integrity verification screen, PII/secret redaction | `Support/Audit/AuditLogger.php`, `Support/Logging/Redactor.php` |
| Settings | Encrypted secret values, masking, audited changes (values of secrets never logged) | `Support/Settings/SettingsRepository.php` |
| Security headers | CSP with nonce and no `unsafe-eval` (Alpine CSP build), frame-ancestors none, nosniff, HSTS on TLS, no-store on staff pages | `SecurityHeaders.php` |
| Health | `/up` liveness, `/ready` DB/cache/storage/migration checks | `HealthController.php` |
| API | `/api/v1/meta` (versioned, rate-limited) | `routes/api.php` |
| Design system | Ivory/indigo/gold tokens, Inter + Noto Sans Devanagari self-hosted, buttons, fields with accessible errors, cards, badges, tables, loading/empty/error states; responsive public + admin layouts with mobile drawer and safe-area padding | `resources/css/app.css`, `resources/views/components`, `/design-system` |
| Flutter theme | Same tokens in Dart, 48 dp touch targets | `mobile/lib/theme/app_theme.dart` |
| Owner photos | 9 originals stored in `owner-assets/photos/`; web-optimised WebP/JPEG derivatives in `backend/public/images/owner/` | |

## Test results (actual runs, MariaDB 11.4.13)
- `php artisan test`: **32 passed (166 assertions)**. See `phase-2/test-run.txt`.
- Includes a real concurrency test: 6 OS processes × 25 audit appends → 150 rows, no forked chain, verification OK; repeated 5× without failure.
- `flutter test`: 1 passed. `flutter analyze`: "No issues found" (the analysis server also hit the sandbox's open-file limit; re-run on a normal machine to confirm).
- `composer audit`: no advisories. `npm audit`: 0 vulnerabilities.

## UI / accessibility checks (actual, Chromium headless 156 via Playwright)
Pages: home, design system, staff login, admin dashboard, team, settings, audit, at 1440×900 and 390×844.
Signed in through the real password + TOTP flow.
- Final run: **15 checks, 0 axe-core WCAG 2.0/2.1/2.2 A/AA violations, 0 horizontal overflow, 0 console errors** (`phase-2/report.json`).
- Found and fixed during the run: scrollable tables not keyboard-focusable; a visually-hidden table label causing 43 px horizontal overflow on phones.
- Screenshots: `phase-2/*.png`.
- Limits: automated axe checks cover only part of WCAG. Manual screen-reader and keyboard-only passes, plus real-device checks, are still to be done.

## Not yet done (by design, later phases)
Customer OTP login, staff recovery codes, email delivery of invitations (needs SMTP, phase 3), the provider/deployment setup screen (phase 10).
