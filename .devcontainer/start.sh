#!/usr/bin/env bash
# Starts the website (port 8000) and the queue worker (emails, sign-in codes, notifications).
# Runs on every codespace start; run it by hand to restart. Logs: backend/storage/logs/{serve,queue}.log
cd "$(dirname "$0")/../backend" || exit 0
if [ ! -f .env ] || [ ! -d vendor ]; then
  echo "Setup has not finished yet. Run: bash .devcontainer/setup.sh"
  exit 0
fi
if [ -n "${CODESPACE_NAME:-}" ]; then
  # the address changes when a new codespace is created
  sed -i "s|^APP_URL=.*|APP_URL=https://${CODESPACE_NAME}-8000.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:-app.github.dev}|" .env
fi
php artisan config:clear >/dev/null 2>&1 || true
pkill -f "artisan serve" 2>/dev/null || true
pkill -f "artisan queue:work" 2>/dev/null || true
mkdir -p storage/logs
# setsid keeps both running after this script (and the terminal) ends
setsid nohup php artisan serve --host=0.0.0.0 --port=8000 > storage/logs/serve.log 2>&1 < /dev/null &
setsid nohup php artisan queue:work --sleep=2 --tries=3 > storage/logs/queue.log 2>&1 < /dev/null &
sleep 2
echo "Website running on port 8000 (Ports tab). Emails and sign-in codes: port 8025."
