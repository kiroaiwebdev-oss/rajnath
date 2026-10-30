#!/usr/bin/env bash
# Full check inside a codespace.
#   bash scripts/codespace-test.sh            backend tests + asset build + deploy script syntax
#   bash scripts/codespace-test.sh --app      also the Flutter app (installs Flutter once, ~1.5 GB)
#   bash scripts/codespace-test.sh --docker   also builds the production Docker image and starts it
set -uo pipefail
cd "$(dirname "$0")/.."
ROOT=$(pwd)
FAIL=0
step() { echo; echo "==> $*"; }
result() { if [ "$1" -eq 0 ]; then echo "PASS: $2"; else echo "FAIL: $2"; FAIL=1; fi; }

step "Backend tests (PHPUnit, all suites incl. PDFs with Chromium)"
(cd backend && XDEBUG_MODE=off php artisan test --compact); result $? "backend tests"

step "Asset build (Vite)"
(cd backend && npm run build >/dev/null); result $? "asset build"

step "Deploy scripts syntax"
for f in deploy/*.sh; do bash -n "$f" || FAIL=1; done; result $FAIL "deploy scripts"

if [[ " $* " == *" --app "* ]]; then
  step "Flutter app"
  if ! command -v flutter >/dev/null; then
    git clone --depth 1 -b stable https://github.com/flutter/flutter.git "$HOME/flutter" >/dev/null 2>&1
    export PATH="$HOME/flutter/bin:$PATH"
  fi
  (cd mobile && flutter pub get >/dev/null && flutter analyze); result $? "flutter analyze"
  (cd mobile && flutter test); result $? "flutter tests"
fi

if [[ " $* " == *" --docker "* ]]; then
  step "Production Docker image and stack (deploy/)"
  if [ -e deploy/.env.production ]; then
    echo "deploy/.env.production exists; not touching it. Skipping the Docker check."
  else
    cp deploy/.env.production.example deploy/.env.production
    sed -i -e "s|^DOMAIN=.*|DOMAIN=localhost|" -e "s|^APP_URL=.*|APP_URL=https://localhost|" \
      -e "s|^APP_KEY=.*|APP_KEY=base64:$(head -c 32 /dev/urandom | base64)|" \
      -e "s|^DB_PASSWORD=.*|DB_PASSWORD=ci-db-pass|" -e "s|^MARIADB_PASSWORD=.*|MARIADB_PASSWORD=ci-db-pass|" \
      -e "s|^MARIADB_ROOT_PASSWORD=.*|MARIADB_ROOT_PASSWORD=ci-root-pass|" deploy/.env.production
    ln -sfn .env.production deploy/.env
    DC="docker compose -f deploy/docker-compose.yml -p arjci"
    $DC build; result $? "docker build"
    $DC up -d && sleep 25
    $DC exec -T app php artisan migrate --force --seed >/dev/null; result $? "migrate + seed in the container"
    code=$(curl -sk -o /dev/null -w '%{http_code}' https://localhost/ready); [ "$code" = "200" ]; result $? "https://localhost/ready answered $code"
    $DC down -v >/dev/null 2>&1
    rm -f deploy/.env deploy/.env.production
  fi
fi

echo
[ $FAIL -eq 0 ] && echo "ALL CHECKS PASSED" || echo "SOME CHECKS FAILED (see above)"
exit $FAIL
