# Phase 8 — Operations: evidence

Automated tests: `backend/tests/Feature/Ops/OperationsTest.php` (20 tests); the full suite has 152 tests, all passing.
UI check: `scripts/ui-check/check-ops.mjs` covers 21 pages at desktop and phone sizes, 42 checks in all. Every page returned 200, with 0 axe WCAG 2.2 AA violations, 0 horizontal overflow and 0 console errors (`phase-8/report-ops.json`). Screenshots are in `phase-8/`.

| Feature | What is covered by tests |
|---|---|
| Slot booking | Availability from weekly hours, leave days and the 180-min lead time. Hold → pay → confirmed. A held slot is hidden from everyone. A UNIQUE index blocks double booking. Unpaid hold expires after 15 min. A capture that arrives late is flagged, not confirmed. A paid hold never expires. Wallet-paid booking is confirmed at once. Max 2 holds per customer. A re-used form key is refused. Cancel gives a full refund to the wallet, once only. One reschedule is allowed. Changes are refused within 24 h. Other customers get 404. Admin can manage hours, add a meeting link (https only) and record the outcome. |
| Puja / chadhava | Publishing is blocked without a linked, verified, unexpired supplier. Supplier PDF/image upload is validated. Paid → sankalp (encrypted at rest) → schedule only with a linked, authorised supplier at a future time (IST stored as UTC) → photo proof (re-encoded images only) → completed. Sankalp is locked after scheduling. Proof photos are visible only to the owner and fulfilment staff. Other customers get 404. |
| Reports | The couple report needs two of the customer's own profiles. Both charts and Ashtakoota are computed. Staff write the sections, with a minimum before publishing. HTML is escaped. |
| Support | Ticket linked to an order is checked against the owner. Payment tickets get high priority. Internal notes are hidden from the customer. A closed ticket cannot be replied to. RBAC is enforced. |
| Privacy | Export needs a sign-in within the last 15 min. Delete is blocked while a puja, report, booking, order, delivery, wallet balance or refund is pending. Delete anonymises the account, signs the user out and keeps orders for tax. |
| Rashifal | A different person must publish. Authors without `content.publish` cannot publish. Only the current period is shown. |
| Vrat calendar | Renders Ekadashi and the other vrat dates. Invalid months are rejected. |

Bug found and fixed by the tests: `tickets.status` was too short for `waiting_customer`, so staff replies failed with a 500 error.

Not done in this phase (see the traceability matrix):
- in-app audio/video calling (needs a LiveKit server)
- push notifications (needs Firebase)
- AI assistant (needs a provider key)
- team practitioners and payouts
- app screens for payment, gems and palmistry
- full Hindi interface (Rashifal and the calendar are in Hindi; the rest of the UI is English)
