# Incident Response (initial version)

Applies to suspected breaches, leaked credentials, audit-chain breaks, payment anomalies and data-exposure reports.

## 1. Detect and record
- Note time (IST and UTC), reporter, what was observed. Do not delete logs.
- Audit chain broken (Admin → Audit log → Verify integrity): record the first broken record number.

## 2. Contain (owner or administrator)
- Suspend affected staff accounts (Admin → Team → Suspend & sign out). Suspension deletes their web sessions and API tokens.
- Rotate exposed secrets: provider keys in Admin → Deployment setup (from phase 10); `APP_KEY` only with the re-encryption procedure in `docs/ops` (an APP_KEY change without re-encryption makes stored secrets unreadable).
- If customer funds may be affected: pause payouts and refunds (finance), keep payments running unless the gateway itself is compromised.

## 3. Investigate
- Export the audit log range and web/server logs; preserve database backups from before the incident.
- Identify affected records and people.

## 4. Notify
- Personal-data breaches: the owner's legal adviser decides on notification under India's DPDP Act and any other applicable law. The platform does not decide this automatically.
- Payment incidents: contact Razorpay support.

## 5. Recover and learn
- Restore from a verified backup if data was altered (`docs/ops/backup-restore.md`, phase 12).
- Write a short post-incident note: cause, impact, fix, prevention.
