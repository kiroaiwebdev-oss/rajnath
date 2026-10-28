# Data Model (logical)

Conventions: `id` BIGINT unsigned PK; public references use ULIDs (`ref`) so internal IDs are
never exposed (IDOR defence); `*_minor` BIGINT money in paise with `currency` CHAR(3);
timestamps UTC; soft deletes only where retention requires; financial and audit tables are
append-only (no UPDATE/DELETE from application code).

## Identity
- `users` (ref, name, email, phone_e164, email_verified_at, phone_verified_at, status, locale, kind: customer|staff|practitioner)
- `otp_challenges` (channel, destination_hash, code_hash, purpose, attempts, expires_at, consumed_at)
- `user_devices` / `personal_access_tokens` (device name, platform, push_token, last_seen_at, revoked_at)
- `mfa_secrets` (encrypted TOTP secret, recovery codes hashed)
- `roles`, `permissions`, `model_has_roles` (spatie)
- `birth_profiles` (user_id, label, name, gender, local_date, local_time, time_accuracy, place_id, lat, lng, tz_name, utc_instant, tz_resolution)
- `places` (GeoNames-derived: name, admin1, country, lat, lng, tz_name, population)

## Catalogue
- `service_families` (consultation, report, bundle, puja, chadhava, written_question, vastu, muhurat)
- `offerings` (family, slug, status: draft|active|paused|retired, requires_supplier, visibility)
- `offering_translations` (locale, title, summary, scope, exclusions, disclaimer)
- `offering_variants` (mode, tier, duration_min, participants, priority, price_minor, currency)
- `bundle_items` (bundle_offering_id, item_offering_id)
- `price_versions` (variant_id, price_minor, effective_from, approved_by)

## Orders and ledger
- `orders` (ref, user_id, status, total_minor, currency, idempotency_key)
- `order_items` (offering snapshot JSON, variant snapshot, price_minor, tax_minor, policy_versions JSON)
- `ledger_accounts` (owner_type/id, kind: customer_wallet|customer_reserved|revenue|practitioner_payable|supplier_payable|gateway_clearing|refunds|commission|tax_payable|bonus_liability, currency, balance_minor cached)
- `ledger_transactions` (ref, kind, idempotency_key UNIQUE, occurred_at, memo)
- `ledger_entries` (transaction_id, account_id, direction debit|credit, amount_minor > 0) — sum(debits)=sum(credits) per transaction enforced in code + verified by a reconciliation job
- `reservations` (account_id, amount_minor, status held|consumed|released, subject_type/id)

## Payments
- `payments` (provider, provider_order_id, provider_payment_id, amount_minor, status, raw_status, verified_at)
- `payment_events` (provider_event_id UNIQUE, type, payload_hash, signature_ok, processed_at)
- `refunds` (payment_id, amount_minor, reason, status, requested_by, approved_by, provider_refund_id)
- `disputes` (payment_id, provider_dispute_id, status, hold_transaction_id)
- `settlements`, `settlement_lines`, `reconciliation_findings`

## Practitioners and payouts
- `practitioners` (user_id, status: applied|under_review|approved|suspended|offboarded, tier, bio, languages, expertise)
- `practitioner_documents` (type, private path, reviewed_by, decision)
- `practitioner_rates` (mode, tier, per_minute_minor | fixed_minor, effective_from)
- `availability_rules`, `availability_exceptions` (leave), `slots` (start_utc, end_utc, capacity, booked)
- `commission_policies` (version, rules JSON, effective_from, status draft|pending_approval|active|superseded, maker, checker)
- `earnings` (practitioner_id, source, gross_minor, commission_minor, net_minor, status pending|available|held|paid|clawed_back, available_at)
- `payouts` (practitioner_id, amount_minor, status requested|approved|processing|paid|failed|returned, provider_ref)

## Consultations
- `bookings` (ref, user_id, practitioner_id, variant snapshot, slot_id, status, start_utc, participants JSON, reschedule_count)
- `consultation_sessions` (booking_id, room_name, status created|ringing|active|reconnecting|ended|failed, started_at, ended_at, billed_seconds, end_reason)
- `media_events` (provider_event_id UNIQUE, session_id, type, occurred_at, received_at, applied)
- `billing_ticks` (session_id, seq UNIQUE per session, seconds, amount_minor) — server-computed
- `chat_messages` (session_id, sender, body encrypted, created_at)
- `written_questions` (order_item_id, question, birth_profile snapshot, assigned_to, due_at, answer, status)

## Reports
- `report_definitions` (code, version, input_schema JSON, method_class, languages enabled, preparation: automated|expert)
- `report_jobs` (order_item_id, definition_version, input snapshot, status queued|computing|review|revision|approved|rendered|delivered|failed, reviewer)
- `report_files` (job_id, locale, private path, sha256, pages)
- `interpretation_texts` (key, locale, body, source: original|licensed, reviewed_by, version)

## Content
- `content_items` (type: horoscope|transit|blog|page|faq|guide, scope keys e.g. sign/period, status, effective_from, expires_at)
- `content_versions`, `content_translations`, `media_assets` (owner-approved flag, licence note)

## Puja
- `suppliers` (kind: temple|priest|vendor, legal name, contact, status)
- `supplier_authorizations` (document path, scope, valid_from/to, verified_by)
- `puja_orders` (order_item_id, supplier_id, event_date, sankalp JSON, status, evidence)
- `puja_evidence` (private path, type, uploaded_by, verified_by)
- `shipments` (courier, awb, status, events)

## Support, notifications, privacy, AI, audit
- `tickets`, `ticket_messages` (internal flag), `ticket_links`
- `notifications_outbox` (channel, template, dedupe_key UNIQUE, status, attempts, last_error)
- `privacy_requests` (type export|delete, status, verified_at, legal_hold, steps JSON, completed_at), `retention_policies`, `legal_holds`, `consents`
- `assistant_documents`, `assistant_chunks`, `assistant_conversations`, `assistant_usage`
- `audit_logs` (actor, action, subject, before/after redacted JSON, ip, prev_hash, hash) — hash-chained
- `settings` (key, value encrypted when secret, updated_by)
