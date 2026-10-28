# Razorpay setup (owner)

Everything is done in **Admin → Payments setup**. No files need editing. Saving asks for your
password and authenticator code.

1. Razorpay Dashboard → switch to **Test mode** → Account & Settings → API keys → generate a key.
2. Paste the **Key ID** (`rzp_test_…`) and **Key secret**. Click **Test connection** — it calls
   Razorpay with the keys and reports whether they were accepted.
3. Razorpay Dashboard → Webhooks → Add: URL shown on the setup page
   (`https://astrorajnathjha.com/api/webhooks/razorpay`), a secret you choose, and the events listed
   on the page. Paste the same secret into **Webhook secret**.
4. Tick **Allow customers to pay online** and save.
5. Admin → Finance → Recharge options: add the amounts customers can pay (bonus optional).
6. Make a test payment with Razorpay's test cards/UPI. Check Admin → Finance → Payments shows it as
   Paid and a webhook was received.

## Going live
Only the owner can do this. Generate live keys in Razorpay (live mode, after KYC), save them,
then in **Test or live mode** choose Live, set the **largest single payment** allowed and type
`LIVE`. Test keys are refused in live mode and live keys in test mode. Start with a low limit and
one real payment of your own, then refund it through Admin → Finance to confirm the full cycle.

## Daily operation
- The scheduler re-checks unfinished payments and refunds every 5 minutes
  (`arj:payments-reconcile`) and verifies the ledger daily at 02:30 IST (`arj:ledger-verify`).
- Refunds: anyone with *refunds.request* raises one from the payment page; a **different** person
  with *refunds.approve* approves it (owner may self-approve; this is recorded). Only unspent paid
  wallet balance can be refunded.
- Disputes arrive by webhook; the disputed amount is held from the customer's paid balance.
  Respond in the Razorpay Dashboard.
- Admin → Finance → Overview lists findings (mismatches, unknown webhooks, failed refunds…) to
  resolve with a note.
