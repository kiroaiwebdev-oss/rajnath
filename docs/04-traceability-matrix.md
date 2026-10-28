# Requirement → Feature Traceability Matrix

Status values: `not started` · `in progress` · `implemented` (code + automated tests pass) ·
`verified` (owner/external acceptance evidence recorded). Nothing is marked `verified` without
evidence linked in `docs/evidence/`.

| Area (brief §) | Inventory IDs | Phase | Status | Evidence |
|---|---|---|---|---|
| Toolchain & planning | — | 1 | implemented | `docs/evidence/phase-1.md` |
| Architecture & data model docs | — | 1 | implemented | `02-architecture.md`, `03-data-model.md` |
| Foundation: roles, audit, settings, design system (§6, §18) | ID-07..09, AD-15..16 | 2 | implemented | `docs/evidence/phase-2.md` |
| Accounts & OTP (§7) | ID-01..06 | 3 | implemented (web + API); real provider send not yet verified; export/deletion in phase 10 | `docs/evidence/phase-3-4.md` |
| Email/SMS provider configuration (§16, §17) | AD-17 (SMTP, OTP part), NO-01 (email, SMS send) | 3 | implemented — real-account send not yet verified | `docs/evidence/messaging.md` |
| Catalogue & public website/PWA (§3, §6) | AD-01..05, ED-06 | 4 | implemented (English); Hindi + media library pending | `docs/evidence/phase-3-4.md` |
| Wallet, payments, ledger (§9) | FI-01..08 | 5 | implemented (Razorpay sandbox via fakes; live not verified; coupons FI-13 not started; GST invoice not started) | `docs/evidence/phase-5-7-11-12.md` |
| Consultations & calling (§8) | CO-01..14, PR-01..07 | 6/8 | partial: slot booking for Acharya Ji (hours, leave, 15-min hold, pay, cancel→wallet, 1 reschedule, no double booking) implemented; meeting link entered by staff; in-app audio/video (LiveKit) and team practitioners/PR-* not started | `docs/evidence/phase-8-operations.md` |
| Astro engine, calculators, Panchang (§11, §12) | CA-01..24, PA-01, PA-02, PA-04 (partial) | 7 | implemented; PA-03 lunar months, PA-05 event muhurats, PA-06 festival calendars, Choghadiya/Hora not started | `docs/astro/methods.md` |
| Reports & bundles (§10) | RP-01..25 | 8 | implemented as expert-prepared reports (chart computed, staff write sections, customer prints/saves PDF); no auto-generated text, no server-side PDF | `docs/evidence/phase-8-operations.md` |
| Puja/Chadhava (§14) | PJ-01..10 | 8 | implemented: paid booking → encrypted sankalp → authorised priest/temple + time → photo proof → completed; publishing blocked without verified supplier; real suppliers not yet added | `docs/evidence/phase-8-operations.md` |
| Gemstone store (owner request 28 Oct) | products, stock, shipping, dispatch | — | implemented (online payment via Razorpay; courier API not integrated, AWB entered manually) | `docs/evidence/final/` |
| Palmistry photo report (owner request 28 Oct) | paid order → photo upload → expert report | — | implemented (expert-written; no automated palm analysis; PDF via browser print) | `backend/tests/Feature/Store/StoreAndPalmTest.php` |
| Internal security review | ASVS L2 selected controls, route audit, secret scan | — | done internally; independent review pending | `docs/security/security-review.md` |
| Horoscope/transit publishing (§13) | ED-01..05 | 8 | Rashifal (daily/weekly/monthly/yearly, author ≠ publisher, stale hidden) and vrat calendar (udaya tithi) implemented; transit articles not started; regional festival rules not applied | `docs/evidence/phase-8-operations.md` |
| Support & notifications (§16) | SU-01..03, NO-01..02 | 8 | support tickets with internal notes + email notices implemented; push (FCM) not started | `docs/evidence/phase-8-operations.md` |
| AI assistant (§15) | AI-01..03 | 9 | not started | |
| Admin control centre (§17) | AD-06..14, AD-17..18 | 10 | not started | |
| Payouts, commission, reconciliation (§9) | FI-09..12, PR-08..09 | 10 | not started | |
| Privacy & deletion (§16) | PV-01..04 | 8 | self-service export (JSON) and deletion by anonymisation (blocked while anything is pending; recent sign-in required) implemented; staff privacy queue not built | `docs/evidence/phase-8-operations.md` |
| Android app (§19) | AN-01..04, AN-06 (partial), AN-08..09 (partial), AN-11, AN-13 | 11 | partial: signed APK/AAB built; payments, calls (AN-07), push (AN-10) not started; physical devices not tested | `docs/release/signing.md` |
| Deployment & operations (§20) | OP-01..03, OP-05, OP-07 (scripts), OP-10 | 12 | files written; not executed (no Docker here); load test, monitoring, restore rehearsal pending on VPS | `docs/ops/vps-deployment.md` |
| Signing & handover (§22, §23) | AN-13 | 12 | release-signed APK + AAB verified; keystore handed over in workspace (not in Git) | `docs/release/signing.md` |

## External acceptance (owner-provided, per agreement of 27 Oct 2026)

The owner has taken responsibility for: physical Android phone testing, live payment
approval, VPS/domain access, real pandit/temple agreements, independent security review and
expert translation review. These rows stay `not started` until evidence is supplied.

| Item | Status |
|---|---|
| Physical device tests (≥ 2 phones) | not started |
| Live Razorpay approval + limits | not started |
| VPS + DNS access | not started |
| Supplier/temple authorizations | not started (admin upload ready: Admin › Priests & temples) |
| Independent security review | not started |
| Translation review (10 languages) | not started |
| Content claims confirmed by owner (biography, awards, counts) | verified — owner confirmation in chat, 28 Oct 2026 |
