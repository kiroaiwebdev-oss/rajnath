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

## 2. First install
```bash
sudo git clone https://github.com/kiroaiwebdev-oss/rajnath.git /opt/arj && cd /opt/arj
git checkout platform-v1
cp deploy/.env.production.example deploy/.env.production && chmod 600 deploy/.env.production
nano deploy/.env.production          # set passwords (long random), DOMAIN
docker compose -f deploy/docker-compose.yml run --rm app php artisan key:generate --show   # paste into APP_KEY
deploy/deploy.sh
docker compose -f deploy/docker-compose.yml exec app php artisan arj:bootstrap-owner you@example.com "Your Name"
```
Open the printed link within 30 minutes. Then set up the providers:
- **Admin → Email & SMS** (SMTP, Twilio or Fast2SMS)
- **Admin → Payments setup**: Razorpay test keys first. Add the webhook URL shown on that page in the Razorpay dashboard.

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
