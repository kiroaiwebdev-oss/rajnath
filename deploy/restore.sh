#!/usr/bin/env bash
# Restore a backup made by backup.sh. Usage: deploy/restore.sh 20261028T021500Z
# Puts the site in maintenance mode, restores the database, private storage and uploads, runs migrations.
set -euo pipefail
cd "$(dirname "$0")"
ln -sfn .env.production .env
TS=${1:?timestamp required}; DEST=${BACKUP_DIR:-/opt/arj-backups}
(cd "$DEST" && sha256sum -c "$TS.sha256")
docker compose exec -T app php artisan down --retry=60
gunzip -c "$DEST/db-$TS.sql.gz" | docker compose exec -T db sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"'
if [ -f "$DEST/files-$TS.tar.gz" ]; then
  docker compose run --rm -T --no-deps --entrypoint "" app sh -c 'cd /var/www/backend && rm -rf storage/app/* public/uploads/* && tar -xzf -' < "$DEST/files-$TS.tar.gz"
else   # backups made before uploads were included
  docker compose run --rm -T --no-deps --entrypoint "" app sh -c 'cd /var/www/backend/storage && rm -rf app && tar -xzf -' < "$DEST/storage-$TS.tar.gz"
fi
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan optimize
docker compose exec -T app php artisan up
echo "restored $TS"
