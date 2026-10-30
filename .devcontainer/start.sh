#!/usr/bin/env bash
# Runs every time the codespace starts: the website on port 8000 and a queue worker
# (emails, sign-in codes, notifications). Logs: backend/storage/logs/{serve,queue}.log
cd "$(dirname "$0")/../backend" || exit 0
[ -f .env ] || exit 0
if [ -n "${CODESPACE_NAME:-}" ]; then
  # the codespace URL changes if the codespace is renamed or recreated
  sed -i "s|^APP_URL=.*|APP_URL=https://${CODESPACE_NAME}-8000.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:-app.github.dev}|" .env
fi
php artisan config:clear >/dev/null 2>&1 || true
pkill -f "artisan serve" 2>/dev/null || true
pkill -f "artisan queue:work" 2>/dev/null || true
nohup php artisan serve --host=0.0.0.0 --port=8000 > storage/logs/serve.log 2>&1 &
nohup php artisan queue:work --sleep=2 --tries=3 > storage/logs/queue.log 2>&1 &
echo "Website running on port 8000 (see the Ports tab). Emails and sign-in codes: port 8025."
