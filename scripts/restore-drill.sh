#!/usr/bin/env bash
# Restore into new, isolated Docker resources. Never overwrite an existing environment.
set -euo pipefail
umask 077
backup_file=${1:?Usage: restore-drill.sh BACKUP.tar.gpg}
: "${BACKUP_PASSPHRASE_FILE:?Set the backup passphrase file}"
: "${RESTORE_DIR:?Set a NEW protected directory outside the web root}"
: "${RESTORE_PROJECT:?Choose a NEW name beginning submission-restore-}"
: "${RESTORE_IMAGE:?Set RESTORE_IMAGE to the release runtime-apache image}"
[[ "$RESTORE_PROJECT" =~ ^submission-restore-[a-z0-9-]+$ ]] || { echo 'Invalid isolated project name.' >&2; exit 1; }
project_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
RESTORE_DIR=$(realpath -m "$RESTORE_DIR")
[[ "$RESTORE_DIR" != "$project_root" && "$RESTORE_DIR" != "$project_root"/* ]] || { echo 'Restore directory must be outside the project.' >&2; exit 1; }
[[ ! -e "$RESTORE_DIR" ]] || { echo 'Restore directory already exists; refusing to overwrite it.' >&2; exit 1; }
for resource in "${RESTORE_PROJECT}-db" "${RESTORE_PROJECT}-app"; do
    if docker inspect "$resource" >/dev/null 2>&1; then echo 'A restore container already exists.' >&2; exit 1; fi
done
for volume in "${RESTORE_PROJECT}-db" "${RESTORE_PROJECT}-storage"; do
    if docker volume inspect "$volume" >/dev/null 2>&1; then echo 'A restore volume already exists.' >&2; exit 1; fi
done
if docker network inspect "$RESTORE_PROJECT" >/dev/null 2>&1; then echo 'Restore network already exists.' >&2; exit 1; fi
mkdir -p "$RESTORE_DIR"
RESTORE_DIR=$(realpath "$RESTORE_DIR")
gpg --batch --pinentry-mode loopback --passphrase-file "$BACKUP_PASSPHRASE_FILE" --decrypt "$backup_file" > "$RESTORE_DIR/snapshot.tar"
# Only extract the exact regular files produced by backup.sh; ignore archive links and extra entries.
python3 - "$RESTORE_DIR" <<'PY'
import pathlib, sys, tarfile
root = pathlib.Path(sys.argv[1])
allowed = {'database.sql','storage.tar.gz','public.tar.gz','app.env','compose.env','data-manifest.json','images.json','revision.txt','SHA256SUMS'}
with tarfile.open(root/'snapshot.tar') as archive:
    for name in allowed:
        member = archive.getmember('./'+name)
        if not member.isfile(): raise RuntimeError('Expected regular backup file: '+name)
        (root/name).write_bytes(archive.extractfile(member).read())
PY
rm "$RESTORE_DIR/snapshot.tar"
(cd "$RESTORE_DIR" && sha256sum --check SHA256SUMS)
restore_password=$(openssl rand -hex 24)
printf 'MYSQL_ROOT_PASSWORD=%s\nMYSQL_DATABASE=restored\n' "$restore_password" > "$RESTORE_DIR/mysql.env"
# A dedicated bridge permits the loopback-only HTTP port used for verification.
docker network create "$RESTORE_PROJECT" >/dev/null
docker volume create "${RESTORE_PROJECT}-db" >/dev/null
docker volume create "${RESTORE_PROJECT}-storage" >/dev/null
docker run -d --name "${RESTORE_PROJECT}-db" --network "$RESTORE_PROJECT" --env-file "$RESTORE_DIR/mysql.env" -v "${RESTORE_PROJECT}-db:/var/lib/mysql" mysql:8.0 >/dev/null
for attempt in {1..60}; do
    if docker exec "${RESTORE_PROJECT}-db" sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -h127.0.0.1 -uroot "$MYSQL_DATABASE" -e "SELECT 1"' >/dev/null 2>&1; then break; fi
    [[ $attempt != 60 ]] || { echo 'Restore database did not start.' >&2; exit 1; }
    sleep 2
done
docker exec -i "${RESTORE_PROJECT}-db" sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot restored' < "$RESTORE_DIR/database.sql"
mkdir -p "$RESTORE_DIR/public"
tar --no-same-owner -xzf "$RESTORE_DIR/public.tar.gz" -C "$RESTORE_DIR/public"
# The protected restore umask must not make web assets unreadable to Apache.
find "$RESTORE_DIR/public" -type d -exec chmod 755 {} +
find "$RESTORE_DIR/public" -type f -exec chmod 644 {} +
docker run --rm --entrypoint tar -v "${RESTORE_PROJECT}-storage:/restore" -v "$RESTORE_DIR:/snapshot:ro" "$RESTORE_IMAGE" -xzf /snapshot/storage.tar.gz -C /restore
cp "$RESTORE_DIR/app.env" "$RESTORE_DIR/isolated.env"
cat >> "$RESTORE_DIR/isolated.env" <<EOF

APP_ENV=staging
APP_URL=http://localhost
APP_DEBUG=false
DB_CONNECTION=mysql
DB_HOST=${RESTORE_PROJECT}-db
DB_PORT=3306
DB_DATABASE=restored
DB_USERNAME=root
DB_PASSWORD=$restore_password
MAIL_MAILER=log
PANDORA_ENABLED=false
SESSION_SECURE_COOKIE=false
TRUSTED_HOSTS=
TRUSTED_PROXIES=
RUN_MIGRATIONS=false
EOF
app_options=(--network "$RESTORE_PROJECT" --env-file "$RESTORE_DIR/isolated.env" -v "${RESTORE_PROJECT}-storage:/var/www/html/storage" -v "$RESTORE_DIR/public:/var/www/html/public" -v "$RESTORE_DIR/public/storage:/var/www/html/storage/app/public" -v "$RESTORE_DIR/data-manifest.json:/snapshot-manifest.json:ro")
# The archive contains durable app data, not runtime framework directories.
docker run --rm "${app_options[@]}" --entrypoint sh "$RESTORE_IMAGE" -c 'mkdir -p storage/framework/cache/data storage/framework/views storage/framework/sessions storage/logs'
docker run --rm "${app_options[@]}" --entrypoint php "$RESTORE_IMAGE" artisan migrate --force
docker run --rm "${app_options[@]}" --entrypoint php "$RESTORE_IMAGE" artisan app:data-manifest --verify=/snapshot-manifest.json
docker run -d --name "${RESTORE_PROJECT}-app" "${app_options[@]}" -p 127.0.0.1::80 "$RESTORE_IMAGE" >/dev/null
restore_address=$(docker port "${RESTORE_PROJECT}-app" 80)
for attempt in {1..60}; do
    if curl --fail --silent --max-time 5 "http://$restore_address/up" >/dev/null; then break; fi
    [[ $attempt != 60 ]] || { echo 'Restore application did not become ready.' >&2; exit 1; }
    sleep 2
done
echo "Restore integrity and HTTP readiness verified: http://$restore_address"
# Keep the resources for manual login/download verification. Cleanup commands are in docs/operations.md.
