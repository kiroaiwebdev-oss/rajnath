#!/usr/bin/env bash
# Runs once when the codespace is created (and can be run again by hand at any time):
# PHP extensions, Chromium, the ephemeris engine, dependencies, .env, database and assets.
set -Eeuo pipefail
cd "$(dirname "$0")/.."
ROOT=$(pwd)
STATE="$ROOT/.devcontainer/.state"
mkdir -p "$STATE"
rm -f "$STATE/done" "$STATE/failed"
touch "$STATE/running"
# everything below is shown on screen and saved to .devcontainer/.state/setup.log
exec > >(tee -a "$STATE/setup.log") 2>&1
trap 'echo; echo "SETUP FAILED at line $LINENO. Send the last 40 lines of .devcontainer/.state/setup.log"; touch "$STATE/failed"; rm -f "$STATE/running"' ERR
say() { echo; echo "==> $(date +%H:%M:%S) $*"; }

say "System packages and PHP extensions"
# an expired third-party apt key in some base images must not stop the setup
sudo rm -f /etc/apt/sources.list.d/yarn.list
sudo apt-get update -y || true
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
  libzip-dev libicu-dev libpng-dev libjpeg62-turbo-dev libwebp-dev libfreetype6-dev build-essential unzip curl
missing=()
loaded=$(php -m | tr '[:upper:]' '[:lower:]')
for ext in pdo_mysql bcmath intl zip gd pcntl exif; do
  grep -qx "$ext" <<< "$loaded" || missing+=("$ext")
done
if [ ${#missing[@]} -gt 0 ]; then
  echo "Installing PHP extensions: ${missing[*]}"
  # install-php-extensions (mlocati) brings the build tools and libraries each extension needs;
  # the base image removed phpize's build tools, which made docker-php-ext-install fail.
  sudo curl -fsSL -o /usr/local/bin/install-php-extensions \
    https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions
  sudo chmod +x /usr/local/bin/install-php-extensions
  sudo -E env "PATH=$PATH" IPE_GD_WITHOUTAVIF=1 install-php-extensions "${missing[@]}"
fi
loaded=$(php -m | tr '[:upper:]' '[:lower:]')
for ext in pdo_mysql bcmath intl zip gd pcntl exif; do
  grep -qx "$ext" <<< "$loaded" || { echo "PHP extension $ext is still missing"; false; }
done
printf 'memory_limit = 512M\nupload_max_filesize = 10M\npost_max_size = 96M\nmax_file_uploads = 20\nmax_execution_time = 60\n' \
  | sudo tee /usr/local/etc/php/conf.d/zz-arj-dev.ini >/dev/null

say "Chromium and Noto fonts (for PDF invoices and reports)"
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends chromium fonts-noto-core \
  || echo "WARNING: Chromium could not be installed. The site works; only PDF downloads will not."

say "Swiss Ephemeris engine"
[ -x engine/bin/arj-ephem ] || engine/build.sh

cd "$ROOT/backend"
say "PHP and JS dependencies"
composer install --no-interaction --prefer-dist --no-progress
npm ci --no-audit --no-fund

if [ -n "${CODESPACE_NAME:-}" ]; then
  URL="https://${CODESPACE_NAME}-8000.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:-app.github.dev}"
else
  URL="http://localhost:8000"
fi

say "Environment file"
[ -f .env ] || cp .env.example .env
set_env() {
  if grep -q "^$1=" .env; then sed -i "s|^$1=.*|$1=$2|" .env; else echo "$1=$2" >> .env; fi
}
set_env APP_ENV local
set_env APP_DEBUG true
set_env APP_URL "$URL"
set_env TRUSTED_PROXIES '*'
set_env DB_CONNECTION mariadb
set_env DB_HOST db
set_env DB_PORT 3306
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

say "Waiting for MariaDB"
for i in $(seq 1 60); do
  php -r 'try { new PDO("mysql:host=db;port=3306;dbname=arj", "arj", "arj-dev-password"); exit(0); } catch (Throwable $e) { exit(1); }' && break
  [ "$i" = 60 ] && { echo "MariaDB did not start"; exit 1; }
  sleep 2
done

say "Database: migrate and seed (roles, places, catalogue, Hindi, profile, gallery, legal pages)"
php artisan migrate --force --seed

say "Building CSS and JS"
npm run build

bash "$ROOT/.devcontainer/start.sh"

rm -f "$STATE/running"
touch "$STATE/done"
echo
echo "SETUP FINISHED"
echo "Website: $URL   (Ports tab: 8000)    Emails and sign-in codes: Ports tab, 8025"
echo "Create the owner account:  cd backend && php artisan arj:bootstrap-owner you@example.com \"Your Name\""
echo "Run all tests:             bash scripts/codespace-test.sh"
