# VPS deployment

Tested here: the application, tests and builds. **Not tested here:** Docker, because the build
sandbox cannot run containers. Run the steps below on the VPS and send me any error output.

## 1. Server
- Ubuntu 24.04 LTS, minimum 2 vCPU / 4 GB RAM / 40 GB SSD. Video calling (planned) needs more; it will be measured later, not guessed.
- DNS: `A` records for `astrorajnathjha.com` and `www` pointing to the VPS IP.
- Firewall: allow only 22 (SSH, key-only), 80 and 443.
  ```bash
  sudo ufw allow OpenSSH && sudo ufw allow 80,443/tcp && sudo ufw enable
  ```
- Install Docker Engine and the Compose plugin (docs.docker.com/engine/install/ubuntu).

## 2. First install (one command)
```bash
sudo git clone https://github.com/kiroaiwebdev-oss/rajnath.git /opt/arj && cd /opt/arj
sudo deploy/install.sh astrorajnathjha.com owner@example.com "Acharya Dr. Rajnath Jha"
```
`install.sh` creates `deploy/.env.production` with a new `APP_KEY` and random database passwords,
builds the images, runs migrations and seeders, starts the site with HTTPS (Caddy gets the
certificate automatically once DNS points at the server) and prints a one-time owner link.
Open the link within 30 minutes and set the password and authenticator app.

Everything else is entered in the admin. No file editing is needed:
- **Admin → Settings**: phone numbers, WhatsApp, email, address, map location, social links, Google/Bing verification codes.
- **Admin → Email & SMS**: SMTP (or another mail provider) and Twilio / Fast2SMS. Send the test message on that page.
- **Admin → Payments setup**: Razorpay keys (test first, then live), GSTIN and tax rates, RazorpayX, Shiprocket. Add the webhook URL shown on that page in the Razorpay dashboard.
- **Admin → Push** (Firebase), **Calling** (LiveKit or Zoom/Meet links), **AI assistant** (optional).

In-app video calls on your own LiveKit server need `RTC_DOMAIN` in `deploy/.env.production`
(for example `rtc.astrorajnathjha.com` with its own DNS record), then `deploy/deploy.sh`. See `livekit-on-vps.md`.
More languages: set `SITE_LOCALES=en,hi,bn,…` after the text has been reviewed (see `backend/lang/`).

Website content is also managed in the admin:
- **Admin → About & awards**: biography, stats (years, clients, awards), home-page text, the two portraits, and the lists for education, positions, awards (with photos), expertise, research, books and media. Each text has an optional Hindi box.
- **Admin → Gallery**: albums for pujas and anushthans, awards, events, media and the office. Upload up to 10 photos at a time (resized, location data removed) and add YouTube links. Authors upload; a content publisher publishes and picks albums for the home page.
- **Admin → Settings**: also the opening hours (per day, morning and evening) and the city, state and PIN code.
- **Admin → Catalogue**: each service can have an uploaded photo.

The first deploy fills About and Gallery with the current biography, 14 awards and the office photos. Later deploys never overwrite the owner's edits.

## 2a. Search engines (SEO / AEO / GEO)
The site serves `/sitemap.xml` (all public pages, with Hindi alternates), `/robots.txt`,
`/llms.txt` and `/llms-full.txt` (summaries for AI answer engines), canonical and hreflang tags,
Open Graph cards, geo tags and schema.org data (LocalBusiness, Person, Service, Product, FAQPage, BreadcrumbList).
After going live:
1. Google Search Console: add the domain, paste the verification code in **Admin → Settings**, submit `https://<domain>/sitemap.xml`.
2. Bing Webmaster Tools: the same, with the Bing code.
3. Google Business Profile: use exactly the same name, address and phone as **Admin → Settings**.
4. Test a service page with Google's Rich Results Test.

**APP_KEY warning:** keep a copy of `APP_KEY` in a safe place. Losing it makes saved provider secrets and MFA secrets unreadable.

## 3. Backups
```bash
sudo crontab -e   # add:
15 2 * * * /opt/arj/deploy/backup.sh >> /var/log/arj-backup.log 2>&1
```
Copy `/opt/arj-backups` off the server every day (for example with rclone to Google Drive or S3). Rehearse a restore at least once:
`deploy/restore.sh <timestamp>`.

## 4. Updates and rollback
- Update: `git pull && deploy/deploy.sh`
- Roll back to a previous tag: `deploy/deploy.sh v1.0.0`. The deploy script takes a backup first. If a migration changed data, restore that backup.

## 5. Monitoring
- Point an external uptime monitor (for example UptimeRobot) at `https://astrorajnathjha.com/ready`. Alert by email or SMS on a non-200 response.
- Logs: `docker compose -f deploy/docker-compose.yml logs -f app queue scheduler`.
- The scheduler runs payment reconciliation every 10 minutes. Findings appear in **Admin → Finance**.
