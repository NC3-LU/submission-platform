#!/usr/bin/env bash
set -euo pipefail
umask 077
source scripts/compose-common.sh
: "${BACKUP_DIR:?Set BACKUP_DIR to a protected directory outside the web/project root}"
: "${BACKUP_PASSPHRASE_FILE:?Set BACKUP_PASSPHRASE_FILE to a protected file containing the backup encryption passphrase}"
command -v gpg >/dev/null
BACKUP_DIR=$(realpath -m "$BACKUP_DIR")
case "$BACKUP_DIR/" in "$(pwd -P)/"*) echo 'Backups must be outside the project/web root.' >&2; exit 1;; esac
mkdir -p "$BACKUP_DIR"
backup_tmp=$(mktemp -d "$BACKUP_DIR/.snapshot.XXXXXX")
backup_file="$BACKUP_DIR/submission-$(date -u +%Y%m%dT%H%M%SZ).tar.gpg"
cleanup() {
    rm -rf "$backup_tmp"
    if [[ ${KEEP_MAINTENANCE:-0} != 1 ]]; then
        "${COMPOSE[@]}" start app queue scheduler >/dev/null
        "${COMPOSE[@]}" exec -T app php artisan up >/dev/null
    fi
}
trap cleanup EXIT
"${COMPOSE[@]}" exec -T app php artisan down --retry=60
# Stop HTTP workers too: maintenance mode alone does not drain in-flight uploads.
"${COMPOSE[@]}" stop --timeout 240 app queue scheduler
"${COMPOSE[@]}" exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump -uroot --single-transaction --quick --routines --triggers --events --no-tablespaces --set-gtid-purged=OFF "$MYSQL_DATABASE"' > "$backup_tmp/database.sql"
"${COMPOSE[@]}" run --rm --no-deps --entrypoint tar app -C /var/www/html/storage -czf - app > "$backup_tmp/storage.tar.gz"
# Public includes release assets and host-mounted header images.
tar -C public -czf "$backup_tmp/public.tar.gz" .
cp .env "$backup_tmp/app.env"
cp "${COMPOSE_ENV_FILE:-docker-compose.env}" "$backup_tmp/compose.env"
"${COMPOSE[@]}" run --rm --no-deps --entrypoint php app artisan app:data-manifest > "$backup_tmp/data-manifest.json"
"${COMPOSE[@]}" images --format json > "$backup_tmp/images.json"
git rev-parse HEAD > "$backup_tmp/revision.txt"
(cd "$backup_tmp" && sha256sum database.sql storage.tar.gz public.tar.gz app.env compose.env data-manifest.json images.json revision.txt > SHA256SUMS)
tar -C "$backup_tmp" -cf - . | gpg --batch --yes --pinentry-mode loopback --passphrase-file "$BACKUP_PASSPHRASE_FILE" --symmetric --cipher-algo AES256 --output "$backup_file"
echo "Encrypted snapshot: $backup_file"
