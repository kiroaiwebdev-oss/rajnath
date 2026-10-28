#!/usr/bin/env bash
# Restore a backup made by backup.sh. Usage: deploy/restore.sh 20261028T021500Z
# Puts the site in maintenance mode, restores DB + storage, runs migrations, brings it back.
set -euo pipefail
cd "$(dirname "$0")"
TS=${1:?timestamp required}; DEST=${BACKUP_DIR:-/opt/arj-backups}
sha256sum -c "$DEST/$TS.sha256"
docker compose exec -T app php artisan down --retry=60
gunzip -c "$DEST/db-$TS.sql.gz" | docker compose exec -T db sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"'
docker compose run --rm -T --no-deps app sh -c 'rm -rf /var/www/backend/storage/app && tar -C /var/www/backend/storage -xzf -' < "$DEST/storage-$TS.tar.gz"
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan up
echo "restored $TS"
