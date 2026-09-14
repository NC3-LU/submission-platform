#!/usr/bin/env bash
set -euo pipefail
source scripts/compose-common.sh
: "${BACKUP_DIR:?Configure protected backup storage before deployment}"
: "${BACKUP_PASSPHRASE_FILE:?Configure a backup encryption passphrase file}"
: "${HEALTH_URL:?Set HEALTH_URL to the host Apache HTTPS /up endpoint}"
release_revision=${RELEASE_REVISION:-$(git rev-parse HEAD 2>/dev/null || true)}
[[ $release_revision =~ ^[0-9a-f]{40}$ ]] || {
    echo 'Set RELEASE_REVISION to the full reviewed commit SHA when deploying a source artifact.' >&2
    exit 1
}
old_container=$("${COMPOSE[@]}" ps --all -q app)
old_image_id=$(docker inspect --format '{{.Image}}' "$old_container")
# Preserve the running image before a build can replace a reused release tag.
old_image="submission-platform:pre-deploy-$(date -u +%Y%m%dT%H%M%SZ)"
docker tag "$old_image_id" "$old_image"
export APP_IMAGE=${RELEASE_IMAGE:-submission-platform:${release_revision:0:12}-$(date -u +%Y%m%dT%H%M%SZ)}
sh scripts/ensure-web-root-traversable.sh "$(pwd -P)"
# Build with the existing site running. No container or volume is removed.
"${COMPOSE[@]}" build app
echo "Rollback image: $old_image"
KEEP_MAINTENANCE=1 bash scripts/backup.sh
trap 'echo "Deployment stopped. Maintenance remains enabled. See docs/operations.md for rollback. Previous image: $old_image" >&2' ERR
"${COMPOSE[@]}" run --rm --no-deps -e RUN_MIGRATIONS=false --entrypoint php app artisan migrate --force
bash scripts/publish-assets.sh "$APP_IMAGE" public
"${COMPOSE[@]}" up -d --no-deps app queue scheduler
"${COMPOSE[@]}" exec -T app php artisan app:health
# The file-scanning stack is independently managed; full health must pass before opening writes.
for attempt in {1..30}; do
    if "${COMPOSE[@]}" exec -T app php artisan app:health --full; then break; fi
    if [[ $attempt == 30 ]]; then exit 1; fi
    sleep 5
done
"${COMPOSE[@]}" exec -T app php artisan up
if ! curl --fail --silent --show-error --max-time 15 --retry 15 --retry-delay 2 --retry-connrefused --retry-all-errors --retry-max-time 120 "$HEALTH_URL" >/dev/null; then
    "${COMPOSE[@]}" exec -T app php artisan down --retry=60
    exit 1
fi
printf '%s\n' "$APP_IMAGE" > .release-image
printf '%s\n' "$release_revision" > .release-revision
trap - ERR
echo "Deployment verified. Release image: $APP_IMAGE; rollback image: $old_image"
