# App API v1 — Phase 9 additions (payments, store, palmistry, bookings, push, assistant)

Base: `/api/v1`. JSON. Customer endpoints need `Authorization: Bearer <token>` (existing OTP sign-in).
Money is always integer paise (`*_minor`). Times are ISO-8601 UTC (`2026-11-03T04:30:00Z`).
Errors: same as today. Validation is `422 {"message", "errors": {field: [msg]}}`; other errors are
`{"error": {"code", "message"}}`; a missing or someone else's resource is `404`.
`key` = a client-generated idempotency UUID, one per purchase attempt (re-sending it returns the same order).

## Public

### GET /meta (extended)
```json
{ "api_version": "v1", "brand": "...", "min_app_version": "1.0.0", "source_code_url": "...",
  "push": { "firebase": { "api_key": "...", "app_id": "1:123:android:abc", "messaging_sender_id": "123", "project_id": "my-proj" } } | null,
  "assistant": { "enabled": true } }
```
`push` is `null` until the owner saves the Firebase Android app config in Admin › Notifications.
These values are public client identifiers, not secrets.

### GET /store/products?category=gemstone
`{ "data": [ Product ] }`. Product:
`{ "ref", "slug", "name", "category", "summary", "description", "weight", "origin", "certification", "price_minor", "in_stock": bool, "max_per_order", "image_url": string|null }`
### GET /store/products/{slug} → `{ "data": Product }`

### GET /practitioners?offering={slug}
Approved team astrologers for a team-consultation offering.
`{ "data": [ { "ref", "display_name", "bio", "languages": [..], "specialties": [..], "experience_years", "photo_url" } ] }`

## Customer (Bearer)

### Push and notifications
- `POST /push-tokens` `{ "token": "<fcm token>", "platform": "android" }` → `204`. Call after sign-in and on token refresh.
- `DELETE /push-tokens` `{ "token" }` → `204`. Call before sign-out.
- `GET /notifications` → `{ "data": [ { "id", "kind", "ref", "title", "body", "link": string|null, "read": bool, "created_at" } ], "unread": 3 }`
- `POST /notifications/{id}/read` → `204`; `POST /notifications/read-all` → `204`

FCM message `data` payload: `{ "kind": "booking|report|puja|palm|ticket|order|general", "ref": "<ulid>", "link": "https://…" }`.
The app opens the matching screen when `kind`/`ref` is known; otherwise it opens Notifications.

### Wallet
`GET /wallet` → `{ "balance_minor", "packages": [ { "id", "amount_minor", "bonus_minor" } ], "transactions": [ { "kind", "amount_minor", "direction": "credit|debit", "memo", "at" } ] }`

### Buying (all return a Checkout)
Checkout = `{ "order": Order, "razorpay": null | { "key", "order_id", "amount", "currency": "INR", "name", "description", "prefill": { "email", "contact" } } }`.
If `razorpay` is null the order is already paid (wallet) or nothing is due. Otherwise open Razorpay Checkout with these
values, then call `/orders/{ref}/verify` with the three `razorpay_*` values returned by the SDK.

- `POST /checkout/service` `{ "variant": "<ref>", "use_wallet": bool, "key" }`. For reports, puja, chadhava and palmistry.
  If the variant is time-slot based you get 422 `variant` and must use `/checkout/booking`.
- `GET /slots?variant=<ref>[&practitioner=<ref>]` → `{ "data": { "2026-11-03": [ { "start": "2026-11-03T04:30Z", "label": "10:00 AM" } ] } }`
  Labels are IST. Variants with `mode` in audio, video, in_person or chat and a `duration_min` are slot-based.
- `POST /checkout/booking` `{ "variant", "start", "practitioner"?, "birth_profile"?, "topic"?, "use_wallet": bool, "key" }` → Checkout + `"booking": { "ref", "status" }`.
  Team-consultation offerings need `practitioner`.
- `POST /checkout/recharge` `{ "package": <id>, "key" }`
- `POST /checkout/product` `{ "product": "<ref>", "quantity": 1, "address": { "name", "phone", "line1", "line2"?, "city", "state", "pincode" }, "key" }`

### Orders
- `GET /orders` → `{ "data": [ Order ] }`
- `GET /orders/{ref}` → `{ "data": Order }`
- `POST /orders/{ref}/verify` `{ "razorpay_order_id", "razorpay_payment_id", "razorpay_signature" }` → `{ "data": Order }`.
  `status` may still be `pending_payment` for a few seconds; poll `GET /orders/{ref}` every 3 s, up to 30 s.
- `POST /orders/{ref}/cancel` → `{ "data": Order }` (unpaid orders only)

Order = `{ "ref", "kind": "service|product|recharge", "status": "pending_payment|paid|cancelled|refunded|partially_refunded", "label",
"subtotal_minor", "wallet_applied_minor", "payable_minor", "refunded_minor", "created_at", "paid_at",
"next": null | { "type": "palm_upload|report_details|puja_details|booking|shipment", "ref" } }`

### Bookings
- `GET /bookings` → `{ "data": [ { "ref", "title", "variant", "mode", "start_utc", "end_utc", "status", "meeting_link", "practitioner": string|null, "practitioner_ref": string|null, "can_cancel": bool, "can_reschedule": bool } ] }`
- `POST /bookings/{ref}/cancel` → `{ "data": Booking }` (full refund to the wallet)
- `POST /bookings/{ref}/reschedule` `{ "start" }` → `{ "data": Booking }`

### Palmistry
- `GET /palmistry` → `{ "data": [ Palm ] }`; `GET /palmistry/{ref}` → `{ "data": Palm }`
- `POST /palmistry/{ref}/photos`, multipart: `left` (image), `right` (image), `dominant_hand` (left|right), `age`?, `gender`? (male|female|other), `language`? (en|hi), `question`?, `consent`=1 → `{ "data": Palm }`

Palm = `{ "ref", "status": "awaiting_photos|submitted|in_review|needs_new_photos|completed", "staff_note", "can_upload": bool, "sections": [ { "title", "body" } ], "completed_at" }`
(`sections` is empty until completed).

### Reports and puja
- `GET /reports`, `GET /reports/{ref}` → `{ "ref", "title", "status": "awaiting_details|in_preparation|completed", "needs_two_profiles": bool, "sections": [ { "title", "body" } ], "completed_at" }`
- `POST /reports/{ref}/details` `{ "profile_a", "profile_b"?, "language": "en|hi", "question"? }`
- `GET /puja`, `GET /puja/{ref}` → `{ "ref", "title", "status": "awaiting_details|requested|scheduled|performed|completed|cancelled", "scheduled_at", "live_link", "customer_note", "video_url", "photo_count" }`
- `POST /puja/{ref}/details` `{ "devotees": [ { "name", "relation"? } ], "gotra"?, "wish"?, "location": "online|home|temple", "preferred_date"?, "address"?, "phone" }`

### Assistant
- `POST /assistant/chat` `{ "message": "...", "conversation"?: "<ref>" }` → `{ "conversation": "<ref>", "reply": "...", "handoff": bool, "disclaimer": "..." }`
  `429` when the daily limit is reached. `503 {"error":{"code":"assistant_unavailable"}}` when no AI provider is configured.
  When `handoff` is true, show a "Talk to our team" button that opens Support (website `/account/support`).

### In-app call (LiveKit)
- Booking items have `meeting_provider`: `livekit` | `zoom` | `google_meet` | `manual` | null, and `call_open`. `call_open` is true from 15 minutes before the start until 30 minutes after the end.
  `meeting_link` is the https link for Zoom, Meet or a manual link (open it in the browser). For `livekit` it is null: use the call token instead.
- `POST /bookings/{ref}/call-token` → `{ "url": "wss://…", "token": "<jwt>", "room": "booking-<ref>", "audio_only": bool, "identity": "c-…" }`.
  Returns 422 `{"error":{"code":"call_not_available","message"}}` when the call is too early, over, not in-app, or not set up.
