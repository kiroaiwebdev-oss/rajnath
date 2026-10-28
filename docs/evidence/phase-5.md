# Evidence — Phase 5 (ledger, wallet, Razorpay payments, refunds, disputes) · 28 Oct 2026

## Built
- **Double-entry ledger** (`app/Domain/Ledger`): integer paise only, balanced transactions, unique
  idempotency key per transaction, row locks in id order, protected accounts cannot go negative,
  running balance on every entry. `arj:ledger-verify` (daily) recomputes everything and raises a
  critical finding on any mismatch.
- **Wallet**: paid (refundable) and bonus (promotional) balances; bonus spent first; reservations
  record the exact cash/bonus split for consume and release; disputes move money to "held".
- **Orders** with immutable offering/variant snapshots; pay from wallet or directly (direct
  payments credit the wallet and are reserved in the same DB transaction); complete consumes,
  cancel releases. Used by bookings/reports in later phases.
- **Razorpay**: orders API, Checkout on web (CSP-safe component) and SDK options in the API; checkout
  signature check **and** server re-fetch before crediting; server-side capture of authorised
  payments; amount/currency/order checks (mismatch → `ambiguous` + critical finding); late
  captures after failure; signed webhooks with event-id replay protection; webhook capture claims
  re-fetched from the API; failed processing returns 500 so Razorpay retries; reconciler every
  5 minutes; abandoned payments cancelled after 24 h.
- **Refunds**: maker/checker with step-up, only unspent paid balance, money moved to "refunds in
  flight" before calling Razorpay, safe retry (checks existing refunds by receipt first), failure
  returns money to the wallet.
- **Disputes**: hold on open, release on won/closed, loss (with shortfall as chargeback loss) on lost.
- **Customer UI**: wallet, recharge options, checkout, payment status with recovery actions,
  receipt (not a tax invoice unless GSTIN set), statement CSV (formula-injection safe).
- **Admin UI**: finance overview (platform/customer balances, ledger check, Razorpay clearing vs
  expected, findings with resolution), payments, payment detail with re-check and refund request,
  refunds queue, disputes, recharge options (`prices.manage` + step-up), Razorpay setup with
  connection test and owner-only live mode with per-payment limit.
- **API v1** for Flutter: wallet, packages, transactions, recharges (Idempotency-Key), payments,
  confirm, refresh. Docs: `docs/api/v1.md`. Owner guide: `docs/ops/razorpay-setup.md`.

## Verified (actual runs, MariaDB 11.4.13, PHP 8.4.23)
- `php artisan test`: **124 passed, 874 assertions** (`phase-5/test-run.txt`); 36 new finance tests:
  ledger invariants and tamper detection; idempotent credits/reservations/ticks; **8 parallel OS
  processes racing 80 reservations against one wallet → exactly 40 succeed, never overspent,
  ledger balanced**; forged checkout signature; forged webhook capture; replayed webhooks;
  out-of-order events; Razorpay outage recovery; lost callback; amount mismatch; live-mode key and
  limit rules; maker/checker, step-up and role isolation; refund failure and transient retry with
  no double refund; disputes lost/won; cross-customer 404s on web and API.
- Razorpay in tests is an in-process fake of the documented REST endpoints and HMAC signatures,
  **not** the Razorpay sandbox.
- Browser (Chromium 156, `scripts/ui-check/check-finance.mjs`): real OTP sign-up, seeded wallet
  data, 13 customer and staff screens at desktop and phone: **26 page checks, 0 axe WCAG 2.2 A/AA
  violations, 0 horizontal overflow, 0 console errors** (`phase-5/report-finance-ui.json`). Fixed
  during the run: phone overflow on finance overview and receipt. Checkout with the Razorpay script
  blocked shows the "could not load" message.
- `composer audit`: no advisories · `npm audit`: 0 vulnerabilities.

## Not done / not verified
- **No call to the real Razorpay test sandbox yet** — needs the owner's test keys. Live payments
  not attempted (needs owner approval and limits).
- Razorpay settlement-report import (bank payouts, fees, GST on fees) and the matching
  reconciliation are not built; current reconciliation covers the platform's own records only.
- Coupons/offers (FI-13), tax invoices with GST computation, practitioner earnings/payouts (Phase 10).
- Flutter screens for the wallet (Phase 11).
