#!/usr/bin/env bash
# Nightly backup: database dump + private storage + uploaded photos, kept 14 days. Cron on the VPS:
#   15 2 * * * /opt/arj/deploy/backup.sh >> /var/log/arj-backup.log 2>&1
# Copy /opt/arj-backups off the server (e.g. rclone to cloud storage) — a backup on the same disk is not enough.
set -euo pipefail
cd "$(dirname "$0")"
ln -sfn .env.production .env
DEST=${BACKUP_DIR:-/opt/arj-backups}; mkdir -p "$DEST"; chmod 700 "$DEST"
TS=$(date -u +%Y%m%dT%H%M%SZ)
docker compose exec -T db sh -c 'mariadb-dump --single-transaction --routines --triggers -uroot -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' | gzip -9 > "$DEST/db-$TS.sql.gz"
docker compose run --rm -T --no-deps --entrypoint "" app tar -C /var/www/backend -czf - storage/app public/uploads > "$DEST/files-$TS.tar.gz"
sha256sum "$DEST/db-$TS.sql.gz" "$DEST/files-$TS.tar.gz" > "$DEST/$TS.sha256"
find "$DEST" -type f -mtime +14 -delete
echo "backup $TS ok"
