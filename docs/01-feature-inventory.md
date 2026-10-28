# Feature and Workflow Inventory

Source: owner brief (27 Oct 2026) + functional benchmark review of astroarunpandit.org
(sitemap of 535 URLs reviewed on 27 Oct 2026). The benchmark is used **only** for functional
breadth. No competitor text, logos, photos, practitioner identities, testimonials, prices or
report content are reused.

Every item has an ID used in `04-traceability-matrix.md`. "Surfaces": W = website (Blade),
C = customer dashboard, P = practitioner portal, A = admin/staff, M = Android (Flutter),
B = backend/API/jobs.

## 1. Identity and accounts (ID-*)

| ID | Feature | Surfaces |
|---|---|---|
| ID-01 | Registration / sign-in with email OTP | W C M B |
| ID-02 | Mobile OTP (India DLT-compliant provider, configurable) | W C M B |
| ID-03 | Account recovery (verified channel + cooldown + notification) | W M B |
| ID-04 | Session / device list, revoke one / all | C M B |
| ID-05 | Customer profile + notification preferences | C M B |
| ID-06 | Multiple birth profiles (self, spouse, child, others) | C M B |
| ID-07 | Staff MFA (TOTP) + step-up re-auth for sensitive actions | A B |
| ID-08 | Roles: owner, administrator, finance, support, content author/reviewer/publisher, fulfilment ops, practitioner, customer | A B |
| ID-09 | Secure owner bootstrap (one-time CLI, no default credentials) | B |

## 2. Practitioners (PR-*)

| ID | Feature | Surfaces |
|---|---|---|
| PR-01 | Application from website and app; owner-created practitioners | W M A B |
| PR-02 | Credential/qualification document review; approval before publication | A B |
| PR-03 | Profile: photo, biography, expertise, languages, tiers | P A W M |
| PR-04 | Modes and rates (chat/audio/video/in-person/written) per tier | P A B |
| PR-05 | Availability, slots, leave, capacity, working hours | P A B |
| PR-06 | Session workspace (join, notes visible only to that practitioner + customer where intended) | P B |
| PR-07 | Written-question fulfilment queue with SLA | P A B |
| PR-08 | Earnings, commission, statements, payout requests | P A B |
| PR-09 | Suspension and offboarding (open obligations handled) | A B |

## 3. Consultations (CO-*)

Benchmark has: celebrity/principal consultation, team (elite / premium / named) astrologers,
normal vs urgent, 30 / 60 min, audio vs video, couple option, ask-a-question (written, 48 h).

| ID | Feature |
|---|---|
| CO-01 | Personal consultation with Dr. Rajnath Jha (principal) |
| CO-02 | Individual and couple consultation (participants configurable) |
| CO-03 | Team astrologer consultations by tier (elite / premium / standard — names configurable) |
| CO-04 | Modes: chat, audio, video, in-person |
| CO-05 | Timing: scheduled, urgent (priority + surcharge), instant when staff online |
| CO-06 | Vastu consultation (remote + on-site variants, intake of plan/photos) |
| CO-07 | Baby Muhurat consultation (with explicit doctor-decides notice) |
| CO-08 | Ask-an-astrologer written questions (N questions, delivery commitment, escalation) |
| CO-09 | Configurable duration, lead time, slot capacity, reschedule, cancel, no-show policy |
| CO-10 | Self-hosted LiveKit rooms, short-lived tokens, webhook-verified media events |
| CO-11 | Server-authoritative per-minute or fixed-price billing with reservation/consume/release |
| CO-12 | Reconnect, disconnect grace, max duration, balance exhaustion, room cleanup |
| CO-13 | Duplicate / out-of-order media event reconciliation |
| CO-14 | No covert recording; recording only with explicit opt-in policy (default off) |

## 4. Commerce, wallet, accounting, payouts (FI-*)

| ID | Feature |
|---|---|
| FI-01 | Recharge packages (configurable), INR minor units, optional bonus credit |
| FI-02 | Razorpay checkout, server-side signature verification, signed webhooks, idempotency |
| FI-03 | Payment states: created / pending / captured / failed / cancelled / ambiguous (auto-reconcile) |
| FI-04 | Direct purchase of reports, pujas, bookings (no wallet required) |
| FI-05 | Double-entry ledger; immutable order and price snapshots |
| FI-06 | Reservations, consumption, release; full/partial refunds with approval |
| FI-07 | Chargebacks/disputes, financial holds |
| FI-08 | Receipts / tax invoices (GST fields configurable), customer statements |
| FI-09 | Versioned commission policies with effective dates, owner approval (maker/checker) |
| FI-10 | Practitioner earnings: pending / available / held, clawbacks, adjustments |
| FI-11 | Payout requests, schedules, approval, provider/manual reference, failure/return |
| FI-12 | Settlement import, reconciliation findings and exception queue |
| FI-13 | Coupons / offers (configurable, abuse limits) |

## 5. Reports and bundles (RP-*)

Each report has its own input schema, method, sections, interpretive text library and tests.

| ID | Report |
|---|---|
| RP-01 | Premium Personalized Kundli |
| RP-02 | Couple Kundli Compatibility |
| RP-03 | Varshphal (Tajik annual chart) |
| RP-04 | Yearly Horoscope Report |
| RP-05 | Soul Purpose (Atmakaraka, Rahu–Ketu axis, 9th house) |
| RP-06 | Career |
| RP-07 | Dhanyog / Finance |
| RP-08 | Love |
| RP-09 | Name Analysis and Correction (expert-prepared, labelled) |
| RP-10 | Fortune |
| RP-11 | Fortune Plus |
| RP-12 | Baby Name (nakshatra syllables + numerology, expert-reviewed) |
| RP-13 | Lal Kitab |
| RP-14 | Rahu–Ketu Transit |
| RP-15 | Jupiter Transit |
| RP-16 | Saturn in Birth Chart |
| RP-17 | Sade Sati |
| RP-18 | Kaal Sarp and Manglik Dosh |
| RP-19 | Bundle: Career Destiny |
| RP-20 | Bundle: Kundli Suraksha |
| RP-21 | Bundle: Lal Kitab Wisdom |
| RP-22 | Bundle: Love Compatibility Kit |
| RP-23 | Seasonal Guidance Package |
| RP-24 | Languages: en, hi, bn, gu, kn, ml, mr, pa, ta, te (each language enabled only after reviewed translation exists) |
| RP-25 | Review/revision queue, protected re-download, branded charts (North/South Indian) |

## 6. Calculators (CA-*)

CA-01 Free Kundli · CA-02 Kundli matching (Ashtakoota) · CA-03 Moon sign · CA-04 Nakshatra ·
CA-05 Lagna/Navamsa · CA-06 Lal Kitab chart · CA-07 Sade Sati · CA-08 Pitra Dosh ·
CA-09 Rahu–Ketu · CA-10 General numerology · CA-11 Name number · CA-12 Destiny number ·
CA-13 Lo Shu grid · CA-14 Personal year · CA-15 Favourable alphabets/numbers ·
CA-16 Memorable periods · CA-17 Predictive personality (traditional, labelled) ·
CA-18 Baby-name generator · CA-19 Lucky/unlucky colours · CA-20 Lucky/unlucky dates ·
CA-21 Vehicle number · CA-22 Rudraksha · CA-23 Love calculator (entertainment, labelled) ·
CA-24 FLAMES (entertainment, labelled).

Each output is tagged `astronomical`, `traditional`, `symbolic` or `entertainment`.

## 7. Panchang, Muhurat, calendars (PA-*)

| ID | Feature |
|---|---|
| PA-01 | Daily location-aware Panchang: sunrise/set, moonrise/set, vara, tithi/paksha, nakshatra/pada, yoga, karana, transition times incl. after-midnight |
| PA-02 | High-latitude / no-event handling |
| PA-03 | Monthly and yearly views; lunar months (amanta/purnimanta), adhik masa |
| PA-04 | Time intervals: Rahu kaal, Yamaganda, Gulika, Abhijit, Brahma muhurta, Choghadiya, Hora (documented methods) |
| PA-05 | Event muhurats: marriage, baby, namkaran, annaprashana, mundan, karnavedha, vidyarambh, yagyopavit — versioned rule sets, expert-reviewed before publication |
| PA-06 | Festival/fasting calendars: Hindu (rule-based), Islamic, Christian, Sikh, Jain, Buddhist (reviewed tables/rules) |
| PA-07 | Ekadashi and vrat lists |

## 8. Horoscopes, transits, editorial (ED-*)

| ID | Feature |
|---|---|
| ED-01 | Daily, weekly, weekly-love, monthly, half-yearly, yearly horoscopes for 12 signs |
| ED-02 | Career, finance, health, education, nakshatra (27), Chinese (12), numerology monthly/yearly (1–9), tarot yearly, Hindi Varshik Rashifal, zodiac guides |
| ED-03 | Transit pages for Sun, Moon, Mars, Mercury, Jupiter, Venus, Saturn, Rahu, Ketu (dates computed from ephemeris; interpretation authored) |
| ED-04 | Workflow: draft → review → approved → scheduled → published → expired; version history |
| ED-05 | Translation workflow, search/filter, stale-content prevention (expired content hidden) |
| ED-06 | Blog, FAQs, biography, gallery, achievements, policies |

## 9. Puja and Chadhava (PJ-*)

| ID | Feature |
|---|---|
| PJ-01 | Catalogue: 9 Grah Shanti + Navgrah Shanti, 12 Lagna pujas, and the 23 named pujas in the brief |
| PJ-02 | Chadhava: Shani, Hanuman Garhi/Bajrangbali, Mahamrityunjaya, Rahu, Maa Baglamukhi, Diwali Lakshmi Ganesh, Karwa Chauth Sindoor Shringar |
| PJ-03 | Supplier/priest/temple registry with authorization documents; an offering cannot be activated without an authorized supplier |
| PJ-04 | Package scope, exclusions, capacity, event dates |
| PJ-05 | Sankalp/participant intake (names, gotra, wishes) |
| PJ-06 | Acceptance → scheduled → performed → evidence uploaded → verified → delivered |
| PJ-07 | Protected evidence (photos/video) access for the customer only |
| PJ-08 | Exceptions, cancellation, refund; optional prasad courier tracking |
| PJ-09 | Supplier settlement |
| PJ-10 | Mandatory disclaimer: no guaranteed outcomes, no substitute for medical/addiction care |

## 10. Support, notifications, privacy, AI (SU-*, NO-*, PV-*, AI-*)

| ID | Feature |
|---|---|
| SU-01 | Tickets: categories, priority, assignment, replies, internal notes, attachments |
| SU-02 | Linked orders/bookings/payments; escalation; close/reopen |
| SU-03 | Authorized financial resolution (refund requests through finance approval) |
| NO-01 | Channels: email, push (FCM), SMS (where approved), in-app; incoming-call push |
| NO-02 | Templates, retries, deduplication, delivery log visible to staff |
| PV-01 | Data inventory, consent records, preferences |
| PV-02 | Protected export (recent auth, expiring link) |
| PV-03 | Deletion/anonymization execution with legal holds and retention policies |
| PV-04 | Processor-side requests, backup-retention treatment, customer-visible outcome |
| AI-01 | English/Hindi/Hinglish assistant over approved knowledge with citations |
| AI-02 | Permission-filtered account help via whitelisted read-only tools; no SQL, no financial actions |
| AI-03 | Rate/cost limits, prompt-injection tests, AI disclosure |

## 11. Admin control centre (AD-*)

AD-01 Branding/contact/social · AD-02 Homepage/navigation · AD-03 Media library ·
AD-04 Pages/biography/blog/FAQ/gallery · AD-05 Service catalogue, packages, prices, activation ·
AD-06 Practitioners · AD-07 Customers/restrictions · AD-08 Bookings/consultations ·
AD-09 Report fulfilment · AD-10 Puja/suppliers · AD-11 Finance (wallet, refunds, payouts, reconciliation) ·
AD-12 Support · AD-13 Privacy · AD-14 AI knowledge · AD-15 Team & permissions ·
AD-16 Audit log · AD-17 Provider configuration (SMTP, OTP, Razorpay, LiveKit, FCM, AI, storage, courier) with encrypted/masked secrets, test-connection, SSRF guard ·
AD-18 Operational health.

## 12. Android app (AN-*)

AN-01 Auth · AN-02 Profiles · AN-03 Catalogue/search · AN-04 Bookings · AN-05 Wallet/recharge (Razorpay SDK) ·
AN-06 Reports/PDF download · AN-07 Chat/audio/video (LiveKit), incoming-call UI (full-screen intent / ConnectionService),
foreground service, lock screen, audio routing, reconnect · AN-08 Support · AN-09 Privacy ·
AN-10 Push notifications · AN-11 Offline/error/retry · AN-12 Practitioner application · AN-13 Signed APK + AAB.

## 13. Operations (OP-*)

OP-01 Docker Compose / provisioning for VPS · OP-02 TLS, firewall · OP-03 Queues, scheduler, dead-letter ·
OP-04 LiveKit + TURN · OP-05 Logs/rotation, health/readiness · OP-06 Monitoring + external alerts ·
OP-07 Backups, restore and rollback rehearsals · OP-08 Load test (20 parallel consultations) ·
OP-09 Security scanning (dependencies, secrets), OWASP ASVS L2 checklist · OP-10 AGPL corresponding-source offer.
