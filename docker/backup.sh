#!/bin/sh
# Backups (B-33): the database and the private files (reports, prescriptions), each night.
# Run from the folder with docker-compose.yml, e.g. from cron:
#   15 2 * * * cd /srv/lab-ordering && ./docker/backup.sh >> /var/log/lab-ordering-backup.log 2>&1
# BACKUP_DIR (default ./backups) and BACKUP_KEEP_DAYS (default 14) can be set in the environment.
# Copy the backup folder off the server too (object storage, another host): a backup on the same
# disk doesn't survive losing that disk. TODO(client): confirm retention and the off-site target.
set -eu

BACKUP_DIR="${BACKUP_DIR:-./backups}"
KEEP_DAYS="${BACKUP_KEEP_DAYS:-14}"
STAMP="$(date -u +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP_DIR"

echo "[$STAMP] Database…"
# --single-transaction: a consistent dump without locking the tables.
docker compose exec -T mysql sh -c 'exec mysqldump --single-transaction --routines --no-tablespaces -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
    | gzip > "$BACKUP_DIR/db-$STAMP.sql.gz"

echo "[$STAMP] Files…"
docker compose exec -T app tar -czf - -C /var/www/html/storage app > "$BACKUP_DIR/files-$STAMP.tar.gz"

# A dump that is suspiciously small means something went wrong: stop before pruning old ones.
if [ "$(gzip -dc "$BACKUP_DIR/db-$STAMP.sql.gz" | head -c 4096 | wc -c)" -lt 1024 ]; then
    echo "Database dump looks empty; keeping older backups." >&2
    exit 1
fi

find "$BACKUP_DIR" -name 'db-*.sql.gz' -mtime +"$KEEP_DAYS" -delete
find "$BACKUP_DIR" -name 'files-*.tar.gz' -mtime +"$KEEP_DAYS" -delete
echo "[$STAMP] Done: $BACKUP_DIR/db-$STAMP.sql.gz, $BACKUP_DIR/files-$STAMP.tar.gz"
