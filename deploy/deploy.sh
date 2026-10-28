#!/usr/bin/env bash
# Zero-surprise update: backup → build → migrate → restart. Rollback: deploy/deploy.sh <previous-git-tag>
set -euo pipefail
cd "$(dirname "$0")/.."
REF=${1:-}
[ -f deploy/.env.production ] || { echo "deploy/.env.production missing"; exit 1; }
deploy/backup.sh
if [ -n "$REF" ]; then git fetch --tags && git checkout "$REF"; fi
docker compose -f deploy/docker-compose.yml build
docker compose -f deploy/docker-compose.yml run --rm public-sync
docker compose -f deploy/docker-compose.yml run --rm app php artisan migrate --force
docker compose -f deploy/docker-compose.yml run --rm app php artisan db:seed --force   # idempotent safe seed (roles, places, catalogue)
docker compose -f deploy/docker-compose.yml run --rm app sh -c 'php artisan config:cache && php artisan route:cache && php artisan view:cache'
docker compose -f deploy/docker-compose.yml up -d
sleep 5
curl -fsS "https://$(grep ^DOMAIN= deploy/.env.production | cut -d= -f2)/ready" && echo " — deploy ok"
