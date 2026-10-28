# Email (SMTP) and SMS OTP setup — owner guide

Everything is entered in **Admin → Email & SMS** (`/admin/providers`). No files need editing.
Only an owner can open this page. Saving or sending a test asks for your password and authenticator code.
Passwords and API keys are stored encrypted. After saving, only the last 4 characters are shown.
Leaving a password field blank keeps the saved value. Tick "Remove the saved value" to delete it.

## Email (SMTP)

| Field | What to enter |
|---|---|
| SMTP host | Your provider's server, e.g. `smtp.zoho.in`, `smtp.gmail.com`, `smtp-relay.brevo.com`, `email-smtp.ap-south-1.amazonaws.com` |
| Port | `587` with TLS (recommended), or `465` with SSL |
| Encryption | TLS (587) or SSL (465). In TLS mode the app refuses to send if the server does not offer encryption |
| Username | Usually the full email address (Gmail/Zoho) or the SMTP username (Brevo/SES) |
| Password | The mailbox password or an **app password** (Gmail and Zoho with 2-step verification need an app password) |
| From email / name | The sender shown to customers. It must be allowed by your provider |

Then click **Send test email**. The table at the bottom shows each attempt and any error message.

If nothing is saved here, the server's `.env` `MAIL_*` values are used. With the default `.env`,
emails are only written to the log and not sent; the page shows "Not configured" in that case.

Security: the SMTP host must resolve to a public internet address, so internal addresses such as
`127.0.0.1`, `10.x` or `169.254.169.254` are refused. To use a mail relay on your own VPS, set
`ARJ_ALLOW_PRIVATE_PROVIDER_HOSTS=true` in `.env` (server administrator only).

## SMS OTP — choose Twilio or Fast2SMS

Choose a provider in the **SMS provider** dropdown. Only that provider's fields are shown.
The other provider's saved details are kept, so you can switch back at any time.

### Fast2SMS (Indian numbers only)
1. In Fast2SMS: **Dev API** → copy the API key.
2. **DLT route (recommended).** Register your header (sender ID) and OTP template on DLT, then add
   them in Fast2SMS **DLT Manager**. Enter:
   - **DLT sender ID / header**: the approved 6-letter header, e.g. `ARJOTP`.
   - **DLT message ID**: the numeric ID of the approved template, shown in DLT Manager.
   - **Template variables**: values for each `{#var#}` in the template, in order and separated by `|`.
     Use `{otp}` for the code and `{minutes}` for its validity.
     Example: for the template "Your OTP is {#var#}, valid for {#var#} minutes", enter `{otp}|{minutes}`.
3. **OTP route**: sends Fast2SMS's own standard OTP message. Only the API key is needed, but Fast2SMS
   may require account or website verification before this route works.
4. Fast2SMS cannot send to numbers outside India. For customers abroad, set **Non-Indian numbers**
   to "Send via Twilio" and fill in the Twilio fields as well.

### Twilio
1. In the Twilio Console → Account info, copy the **Account SID** (starts with `AC`) and **Auth token**.
2. Enter either a **From number** in international format (e.g. `+1…`) or a **Messaging Service SID** (`MG…`).
3. Set the **OTP message text**. It must contain `{otp}`. You can also use `{minutes}` and `{brand}`.
4. For Indian (+91) numbers, the sender and the exact message text must be registered on DLT through
   Twilio. Otherwise Indian operators may block the SMS.

Then click **Send test SMS**. You can enter a 10-digit Indian number, or a number with country code.
The confirmation shows the code that should arrive. One SMS credit is used.

## What "Accepted" means
The provider accepted the message for sending. Delivery to the handset or inbox is not confirmed until
delivery-report webhooks are added (planned with notifications). If a message is accepted but never
arrives, check the provider's own dashboard first.

## Where it is used
Phase 3 uses these settings for customer email and mobile OTP sign-in and account recovery.
Later phases use them for booking, report and payment notifications.
