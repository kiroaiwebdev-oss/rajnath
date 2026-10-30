#!/usr/bin/env bash
# Update (and first deploy): backup → build → migrate → seed → restart → health check.
# Rollback: deploy/deploy.sh <previous-git-tag>
set -euo pipefail
cd "$(dirname "$0")/.."
REF=${1:-}
[ -f deploy/.env.production ] || { echo "deploy/.env.production missing — run deploy/install.sh first"; exit 1; }
ln -sfn .env.production deploy/.env     # docker compose reads ${DOMAIN} etc. from deploy/.env
grep -q '^APP_KEY=base64:' deploy/.env.production || { echo "APP_KEY is empty in deploy/.env.production"; exit 1; }
if grep -q 'change-me' deploy/.env.production; then echo "Replace the change-me passwords in deploy/.env.production"; exit 1; fi
DC="docker compose -f deploy/docker-compose.yml"
PROFILES=""
grep -q '^LIVEKIT_API_KEY=.\+' deploy/.env.production && PROFILES="--profile calls"
# Backup only when a database is already running (not on the very first deploy).
if [ -z "${FIRST_RUN:-}" ] && $DC ps --status running db 2>/dev/null | grep -q db; then deploy/backup.sh; fi
if [ -n "$REF" ]; then git fetch --tags && git checkout "$REF"; fi
$DC build
$DC up -d db redis
$DC run --rm public-sync
$DC run --rm app php artisan migrate --force
$DC run --rm app php artisan db:seed --force   # idempotent safe seed (roles, places, catalogue, Hindi text)
$DC $PROFILES up -d --remove-orphans
DOMAIN=$(grep ^DOMAIN= deploy/.env.production | cut -d= -f2)
for i in $(seq 1 30); do
  if curl -fsS "https://$DOMAIN/ready" >/dev/null 2>&1; then echo "deploy ok — https://$DOMAIN"; exit 0; fi
  sleep 5
done
echo "The site did not answer on https://$DOMAIN/ready yet. Check DNS (A record → this server) and: $DC logs web app"
exit 1
