# First owner account (secure bootstrap)

There are **no default usernames or passwords**. The first owner is created from the server shell.

```bash
cd backend
php artisan migrate --force
php artisan arj:bootstrap-owner you@example.com "Your Name"
```

1. The command prints a one-time link valid for **30 minutes**. Open it in a browser.
2. Choose a password (12+ characters, upper and lower case, number, symbol).
3. Scan the QR code with an authenticator app and enter the 6-digit code.
4. You are signed in to `/admin`. The link cannot be used again.

Afterwards, invite other staff from **Admin → Team**. Each invitation link is shown once to you and
expires after 48 hours; the invitee sets their own password and authenticator.

## Rules enforced
- Every staff sign-in needs password **and** authenticator code. A code cannot be reused.
- Saving settings, changing roles, inviting or suspending staff asks for password + code again if
  the last confirmation was more than 10 minutes ago.
- Only an owner can grant or remove the owner role. The last active owner cannot be removed or suspended.
- Suspending a staff member signs them out everywhere immediately.

## Lost authenticator (recovery)
Run on the server: `php artisan arj:bootstrap-owner other-or-same@email "Name" --force-additional`.
Accepting the link resets that account's password and authenticator. The action is audited.
Self-service recovery codes are planned for phase 3.
