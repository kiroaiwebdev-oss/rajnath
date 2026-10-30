#!/bin/sh
# Runs in every app/queue/scheduler container start: config, route, view and event caches are
# built with the real environment of this container (they would be lost if built elsewhere).
set -e
cd /var/www/backend
mkdir -p storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views storage/logs public/uploads
if [ -n "$APP_KEY" ]; then
  php artisan optimize >/dev/null 2>&1 || php artisan optimize
fi
exec "$@"
