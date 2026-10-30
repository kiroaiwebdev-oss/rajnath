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
| Catalogue & public website/PWA (§3, §6) | AD-01..05, ED-06, RP-24 | 4/13 | implemented: English and Hindi (site chrome, catalogue, option names, FAQs; `?lang=`, saved per customer; API by `Accept-Language`); other 8 languages switch on with `SITE_LOCALES` once their text is entered in Admin › Catalogue › Other languages; Hindi text awaits owner review; media library pending | `tests/Feature/Phase13/ContentAndLanguageTest.php` |
| Wallet, payments, ledger (§9) | FI-01..08, FI-13 | 5/13 | implemented: Razorpay (sandbox via fakes; live not verified); coupons (percent/flat, max discount, minimum order, scope, validity, total and per-customer limits, first order only); GST tax invoices and credit notes (GST-inclusive prices, CGST+SGST or IGST by place of supply, 16-character numbering per financial year, GST kept apart in the ledger); invoice PDF | `tests/Feature/Phase13/CouponsAndInvoicesTest.php` |
| Consultations & calling (§8) | CO-01..14, PR-01..07 | 6/8/9 | implemented: slot booking for Acharya Ji and team astrologers (own hours/leave, approval, tiers), reminders 24 h/1 h; calls in-app (LiveKit), Zoom or Google Meet links created automatically; LiveKit server runs on the owner's VPS (`docs/ops/livekit-on-vps.md`) | `tests/Feature/Phase9/CallingAndPricesTest.php` |
| Astro engine, calculators, Panchang (§11, §12) | CA-01..24, PA-01..07 | 7/13 | implemented: calculators (+ `/tools/match-making` alias), Panchang with Rahu Kaal, Yamaganda, Gulika, Abhijit, Brahma muhurta, Choghadiya (day/night) and Hora (24 planetary hours); amanta and purnimanta lunar months with adhik masa; muhurat finder for vivah, namkaran, annaprashan, mundan, karnavedha, vidyarambh, upanayan (rule set v1, time windows); yearly festival calendar for Hindu, Islamic, Christian, Sikh, Jain, Buddhist. Muhurat rule lists await Acharya Ji's review | `docs/astro/methods.md` |
| Reports & bundles (§10) | RP-01..25 | 8/13 | implemented as expert-prepared reports (chart computed, staff write sections); customer downloads a server-printed PDF (headless Chromium, correct Devanagari) for reports and palmistry reports; no auto-generated text | `tests/Feature/Phase13/CouponsAndInvoicesTest.php` |
| Puja/Chadhava (§14) | PJ-01..10 | 8 | implemented: Single / Couple / Family packages (sankalp for 1 / 2 / up to 6), paid booking → encrypted sankalp → authorised priest/temple + time → photo proof → completed; publishing blocked without verified supplier; real suppliers not yet added | `docs/evidence/phase-8-operations.md` |
| Gemstone store (owner request 28 Oct) | products, stock, shipping, dispatch | 13 | implemented: online payment via Razorpay; courier booked through Shiprocket (order, AWB, pickup, label) with tracking webhook and hourly polling; manual AWB entry still possible; real Shiprocket account not yet connected | `tests/Feature/Phase13/ProvidersCourierPayoutsTest.php` |
| Palmistry photo report (owner request 28 Oct) | paid order → photo upload → expert report | — | implemented (expert-written; no automated palm analysis; PDF via browser print) | `backend/tests/Feature/Store/StoreAndPalmTest.php` |
| Internal security review | ASVS L2 selected controls, route audit, secret scan | — | done internally; independent review pending | `docs/security/security-review.md` |
| Horoscope/transit publishing (§13) | ED-01..05 | 8/13 | implemented: Rashifal daily/weekly/monthly/half-yearly/yearly (yearly readable by year, e.g. Varshik Rashifal 2027), topics love/career/finance/health/education, nakshatra (27), Chinese (12), numerology (Mulank 1–9), tarot; zodiac-sign guides `/rashi/{sign}`; Grah Gochar for all 9 planets (ingresses, retrograde/direct stations, house from each Moon sign, staff-written sign-wise effects) and Navagraha guide. Author ≠ publisher, stale hidden. No content is written yet (staff write it in Admin › Rashifal); regional festival rules partly applied | `docs/astro/methods.md` |
| Support & notifications (§16) | SU-01..03, NO-01..02 | 8/9 | implemented: tickets; every notice stored in an inbox (web + app) and sent by email + FCM push (HTTP v1), preferences respected; real Firebase project not yet connected | `docs/evidence/phase-9.md` | `docs/evidence/phase-8-operations.md` |
| AI assistant (§15) | AI-01..03 | 9 | implemented: Groq / Cloudflare Workers AI / OpenAI / Gemini with fallback order, grounded on catalogue + FAQs + notes, handoff, self-harm safety reply, daily limits, 90-day retention; real keys not yet entered | `docs/evidence/phase-9.md` |
| Admin control centre (§17) | AD-06..18 | 10/13 | implemented: practitioners and astrologer applications, customers, bookings, reports, puja and suppliers, finance (refunds, coupons, invoices, reconciliation), support, privacy requests queue, AI knowledge, team and roles, audit log, providers (email, SMS, Razorpay, RazorpayX, Shiprocket, LiveKit, Zoom, Meet, FCM, AI), operational health (database, cache, disk, queue backlog, failed jobs, scheduler heartbeat, engine, PDF, providers) | `tests/Feature/Phase13/OperationsQueuesTest.php` |
| Payouts, commission, reconciliation (§9) | FI-09..12, PR-08..09 | 9/13 | implemented: commission maker/checker, earnings on completion with 7-day refund window, refund adjustment, payout propose → approve (different person) → paid; payouts sent through RazorpayX (composite payout, idempotent, webhook + polling; failed or reversed payouts return to approved with a finding) or by hand with UTR; ledger + CSV statement; real RazorpayX account not yet connected | `tests/Feature/Phase13/ProvidersCourierPayoutsTest.php` |
| Privacy & deletion (§16) | PV-01..04 | 8/13 | implemented: self-service export (JSON) and deletion by anonymisation; blocked deletions open a case in Admin › Privacy requests (assign, notes, close, staff data export); privacy.approve + step-up runs the deletion for the customer; legal hold blocks deletion | `tests/Feature/Phase13/OperationsQueuesTest.php` |
| Android app (§19) | AN-01..13 | 11/13 | v1.4.0 (source): same features as the website — all 26 calculators (server-built results), vrat calendar, rashi guide, navagraha, FAQ, about/contact, support tickets, privacy export/delete with code, email/mobile change, sign out other devices, PDF downloads (signed links), puja photos, festival city, service mode filter, language choice; earlier: Razorpay payments with coupons, wallet, slot booking, in-app calls, gem store, palmistry upload, reports/puja, invoices on orders, notifications + FCM, assistant, Rashifal (all kinds), Grah Gochar, muhurat, festivals, Choghadiya/Hora, astrologer application; release APK/AAB for 1.4.0 not yet built here (needs the keystore); not yet run on a physical phone or against live keys | `mobile/test/` (55 tests) |
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
