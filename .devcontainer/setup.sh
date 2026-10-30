#!/usr/bin/env bash
# Runs once when the codespace is created: engine, dependencies, .env, database, assets.
set -euo pipefail
cd "$(dirname "$0")/.."
ROOT=$(pwd)

echo "==> Building the Swiss Ephemeris engine"
engine/build.sh

cd "$ROOT/backend"
echo "==> PHP and JS dependencies"
composer install --no-interaction --prefer-dist --no-progress
npm ci --no-audit --no-fund

if [ -n "${CODESPACE_NAME:-}" ]; then
  URL="https://${CODESPACE_NAME}-8000.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:-app.github.dev}"
else
  URL="http://localhost:8000"
fi

[ -f .env ] || cp .env.example .env
set_env() {
  if grep -q "^$1=" .env; then sed -i "s|^$1=.*|$1=$2|" .env; else echo "$1=$2" >> .env; fi
}
set_env APP_ENV local
set_env APP_DEBUG true
set_env APP_URL "$URL"
set_env TRUSTED_PROXIES '*'
set_env DB_HOST db
set_env DB_DATABASE arj
set_env DB_USERNAME arj
set_env DB_PASSWORD arj-dev-password
set_env MAIL_MAILER smtp
set_env MAIL_HOST mailpit
set_env MAIL_PORT 1025
set_env MAIL_FROM_ADDRESS codespace@example.com
set_env QUEUE_CONNECTION database
set_env PDF_CHROME_BINARY /usr/bin/chromium
grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force

echo "==> Waiting for MariaDB"
for _ in $(seq 1 60); do
  mysqladmin ping -h db -u arj -parj-dev-password --silent 2>/dev/null && break
  sleep 2
done

echo "==> Database: migrate and seed (roles, places, catalogue, Hindi, profile, gallery, legal pages)"
php artisan migrate --force --seed

echo "==> Building assets"
npm run build

echo
echo "Setup finished. Website: $URL   Mailpit: port 8025"
echo "Create the owner account:  cd backend && php artisan arj:bootstrap-owner you@example.com \"Your Name\""
echo "Run all tests:             bash scripts/codespace-test.sh"
