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
for _ in $(seq 1 15); do
  code=$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8000/ready || true)
  [ "$code" = "200" ] && break
  sleep 1
done
if [ "$code" = "200" ]; then
  echo "Website is running on port 8000 (answered $code)."
  echo "If the browser shows 404: Ports tab › Forward a Port › 8000, then open the globe icon."
else
  echo "The website did not answer (code $code). Last lines of storage/logs/serve.log:"
  tail -n 20 storage/logs/serve.log
fi
