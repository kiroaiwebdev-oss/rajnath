#!/usr/bin/env bash
# First installation on a fresh VPS (Ubuntu 24.04 with Docker). Run once, from the repository root:
#   deploy/install.sh astrorajnathjha.com owner@example.com "Acharya Dr. Rajnath Jha"
# It creates deploy/.env.production with random passwords and a new APP_KEY, builds and starts
# everything, and prints a one-time link to create the owner account. After that, enter Razorpay,
# email/SMS and other keys in the admin (Admin › Payments setup, Email & SMS, …) — not in files.
set -euo pipefail
cd "$(dirname "$0")/.."
DOMAIN=${1:?usage: deploy/install.sh <domain> <owner-email> "<owner name>"}
EMAIL=${2:?owner email required}
NAME=${3:-Owner}
ENVF=deploy/.env.production
command -v docker >/dev/null || { echo "Install Docker first: https://docs.docker.com/engine/install/ubuntu/"; exit 1; }
rand() { head -c 48 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c "${1:-40}"; }
if [ ! -f "$ENVF" ]; then
  cp deploy/.env.production.example "$ENVF"
  DBP=$(rand 40)
  sed -i \
    -e "s|^DOMAIN=.*|DOMAIN=$DOMAIN|" \
    -e "s|^APP_URL=.*|APP_URL=https://$DOMAIN|" \
    -e "s|^APP_KEY=.*|APP_KEY=base64:$(head -c 32 /dev/urandom | base64)|" \
    -e "s|^DB_PASSWORD=.*|DB_PASSWORD=$DBP|" \
    -e "s|^MARIADB_PASSWORD=.*|MARIADB_PASSWORD=$DBP|" \
    -e "s|^MARIADB_ROOT_PASSWORD=.*|MARIADB_ROOT_PASSWORD=$(rand 40)|" \
    "$ENVF"
  chmod 600 "$ENVF"
  echo "Created $ENVF. KEEP A COPY of APP_KEY somewhere safe: it encrypts saved keys and passwords."
else
  echo "$ENVF already exists — keeping it."
fi
ln -sfn .env.production deploy/.env
FIRST_RUN=1 deploy/deploy.sh
LINK=$(docker compose -f deploy/docker-compose.yml exec -T app php artisan arj:bootstrap-owner "$EMAIL" "$NAME" | grep -o 'https\?://[^ ]*invitation[^ ]*' || true)
echo
echo "Done. Open this link within 30 minutes to set the owner password and authenticator app:"
echo "  ${LINK:-(run: docker compose -f deploy/docker-compose.yml exec app php artisan arj:bootstrap-owner $EMAIL \"$NAME\")}"
echo "Then in the admin: Email & SMS → Payments setup (Razorpay, GST, RazorpayX, Shiprocket) → Push → Calling → AI assistant."
echo "Add the backup cron:  15 2 * * * $(pwd)/deploy/backup.sh >> /var/log/arj-backup.log 2>&1"
