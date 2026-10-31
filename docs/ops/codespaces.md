# Testing in GitHub Codespaces

A codespace runs the whole platform in the browser: website, admin, database, email catcher
and the ephemeris engine. Nothing here touches the live site or real payments.

## 1. Open it
On GitHub: **Code › Codespaces › Create codespace on main**. A 2-core machine is enough; 4-core is faster.
The first start takes 10–15 minutes (it builds the engine, installs packages, fills the
database and builds the CSS/JS). Later starts take under a minute.

The setup log is in `.devcontainer/.state/setup.log`; it ends with `SETUP FINISHED`. If it stopped
with an error, run `bash .devcontainer/setup.sh` again (it continues where it can) and send me
the last lines.

When it is ready, the **Ports** tab shows:
- **8000 — Website + admin.** Open it in the browser.
- **8025 — Mailpit.** Every email the site sends lands here, including sign-in codes.

## 2. Make yourself the owner
In the terminal:
```bash
cd backend
php artisan arj:bootstrap-owner you@example.com "Your Name"
```
Open the printed link (replace `http://localhost:8000` with your port-8000 address if needed),
set a password and scan the QR code with an authenticator app. Then open `/admin`.

## 3. What to try
- **Customer:** sign in at `/account/sign-in` with any email; the code is in Mailpit.
  Save a birth profile, use the calculators, send an enquiry, open a support request.
- **Admin:** About & awards, Gallery (upload photos), Reviews, Legal pages, Settings
  (hours, address), Catalogue, Coupons, Email & SMS.
- **Payments:** add Razorpay **test** keys in Admin › Payments setup, then buy a service with
  Razorpay's test card. Do not use live keys in a codespace.
- **Reviews:** after a test payment, write a review from Account › Reviews, then approve it
  in Admin › Reviews.

## 4. Run every automatic check
```bash
bash scripts/codespace-test.sh            # backend tests, asset build, deploy scripts
bash scripts/codespace-test.sh --app      # also the Flutter app (downloads Flutter once)
bash scripts/codespace-test.sh --docker   # also builds and starts the production Docker stack
```
The last line says `ALL CHECKS PASSED` or lists what failed. Send me the output of anything
that fails.

## Notes
- Logs: `backend/storage/logs/laravel.log`, `serve.log`, `queue.log`.
- Restart the website and queue worker: `bash .devcontainer/start.sh`.
- Start again from an empty database: `cd backend && php artisan migrate:fresh --seed`.
- Codespaces bill by the hour on your GitHub account. Stop the codespace when you are done
  (GitHub › Codespaces › … › Stop); it also stops by itself after 30 minutes without use.
