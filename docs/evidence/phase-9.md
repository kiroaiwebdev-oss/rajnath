# Phase 9: push and email notifications, AI assistant, practitioners and payouts, app parity

Backend tests: `backend/tests/Feature/Phase9/Phase9Test.php` has 16 tests. The full suite is 168 tests, all passing.
App: `flutter analyze` reports no issues and 18 tests pass. Release APK and AAB are signed (cert SHA-256 `855dce96…1c4a3d`).
UI check: `scripts/ui-check/check-p9.mjs` loads 18 pages (desktop and phone). All return 200 with no axe WCAG 2.2 AA violations, no horizontal overflow and no console errors (`phase-9/report-p9.json`).

| Feature | Covered by tests |
|---|---|
| Notifications | Each notice is stored in the inbox and sent by email and FCM (RS256 service-account JWT, HTTP v1). Dead tokens are removed. Preferences mute optional notices only. A device token moves between accounts. Inbox API and web page work. `/meta` exposes only the public Firebase config. Admin settings need step-up, the service account is stored encrypted, and there is a credentials check. Notices exist for booking confirm, cancel, move, link, and 24 h and 1 h reminders (each sent once), payment received with next step, refunds, reports, palmistry, puja and ticket replies. |
| AI assistant | Falls back Groq → Gemini. Answers are grounded on active services only (no drafts). History is kept, and a conversation cannot be taken over by another user. Handoff works through a marker. The self-harm reply makes no AI call. 503 when switched off. Daily guest limit. HTML is stripped. Cloudflare account URL and default models. A provider without a model is skipped. Keys are encrypted and not shown. Knowledge notes go into the prompt. RBAC. |
| Practitioners | Invite as a staff account (password + TOTP). Profile draft → submitted → approved. Self-approval is blocked. Editing an approved profile sends it back for review. Commission needs a second person to approve. A team offering can only be published once a bookable astrologer exists. Each astrologer has their own calendar and slot locks. Astrologers see only their own bookings and cannot close a session before it starts. |
| Payouts | Earning on completion (60% of ₹1,000 = ₹600). A 7-day window before it becomes available. Payout details are encrypted. Proposer ≠ approver ≠ practitioner. UTR is required. Ledger balances to zero and `verify()` is clean. CSV statement. A refund before payout reduces the earning (₹1,000 − ₹400 refund → ₹360). |
| App API | Service purchase gets a Razorpay payload. Forged signature → 422. Verified capture → paid, with next step "palm upload". Palm photos upload from the app. Store buy with address validation. Wallet-paid booking is confirmed at once. Cancel refunds to the wallet. Other users get 404 on orders, bookings and palm readings. |

Bugs found and fixed while testing:
- The assistant counted a failed call (assistant switched off) toward the daily limit.
- The chat form posted to the wrong URL (it used the form's dataset instead of the root's).
- A scrollable table could not be reached by keyboard.

Not done:
- In-app calling (LiveKit server on the owner's VPS).
- Automatic bank payouts (RazorpayX).
- Testing on a physical phone, and with live Firebase, AI or Razorpay keys.
- Full Hindi UI (deferred by the owner).
