# Requirement → Feature Traceability Matrix

Status values: `not started` · `in progress` · `implemented` (code + automated tests pass) ·
`verified` (owner/external acceptance evidence recorded). Nothing is marked `verified` without
evidence linked in `docs/evidence/`.

| Area (brief §) | Inventory IDs | Phase | Status | Evidence |
|---|---|---|---|---|
| Toolchain & planning | — | 1 | implemented | `docs/evidence/phase-1.md` |
| Architecture & data model docs | — | 1 | implemented | `02-architecture.md`, `03-data-model.md` |
| Foundation: roles, audit, settings, design system (§6, §18) | ID-07..09, AD-15..16 | 2 | implemented | `docs/evidence/phase-2.md` |
| Accounts & OTP (§7) | ID-01..06 | 3 | not started | |
| Email/SMS provider configuration (§16, §17) | AD-17 (SMTP, OTP part), NO-01 (email, SMS send) | 3 | implemented — real-account send not yet verified | `docs/evidence/messaging.md` |
| Catalogue & public website/PWA (§3, §6) | AD-01..05, ED-06 | 4 | not started | |
| Wallet, payments, ledger (§9) | FI-01..08, FI-13 | 5 | not started | |
| Consultations & calling (§8) | CO-01..14, PR-01..07 | 6 | not started | |
| Astro engine, calculators, Panchang (§11, §12) | CA-01..24, PA-01..07 | 7 | not started | |
| Reports & bundles (§10) | RP-01..25 | 8 | not started | |
| Puja/Chadhava (§14) | PJ-01..10 | 9 | not started | |
| Horoscope/transit publishing (§13) | ED-01..05 | 9 | not started | |
| Support & notifications (§16) | SU-01..03, NO-01..02 | 9 | not started | |
| AI assistant (§15) | AI-01..03 | 9 | not started | |
| Admin control centre (§17) | AD-06..14, AD-17..18 | 10 | not started | |
| Payouts, commission, reconciliation (§9) | FI-09..12, PR-08..09 | 10 | not started | |
| Privacy & deletion (§16) | PV-01..04 | 10 | not started | |
| Android app (§19) | AN-01..13 | 11 | not started | |
| Deployment & operations (§20) | OP-01..10 | 12 | not started | |
| Signing & handover (§22, §23) | AN-13 | 12 | not started | |

## External acceptance (owner-provided, per agreement of 27 Oct 2026)

The owner has taken responsibility for: physical Android phone testing, live payment
approval, VPS/domain access, real pandit/temple agreements, independent security review and
expert translation review. These rows stay `not started` until evidence is supplied.

| Item | Status |
|---|---|
| Physical device tests (≥ 2 phones) | not started |
| Live Razorpay approval + limits | not started |
| VPS + DNS access | not started |
| Supplier/temple authorizations | not started |
| Independent security review | not started |
| Translation review (10 languages) | not started |
| Content claims confirmed by owner (biography, awards, counts) | verified — owner confirmation in chat, 28 Oct 2026 |
