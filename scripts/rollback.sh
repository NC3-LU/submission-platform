#!/usr/bin/env bash
set -euo pipefail
previous_image=${1:?Usage: rollback.sh PREVIOUS_IMAGE_ID_OR_TAG}
: "${HEALTH_URL:?Set HEALTH_URL to the host HTTPS /up endpoint}"
source scripts/compose-common.sh
docker image inspect "$previous_image" >/dev/null
export APP_IMAGE="submission-platform:rollback-$(date -u +%Y%m%dT%H%M%SZ)"
docker tag "$previous_image" "$APP_IMAGE"
# A failed deployment may have stopped the app. Use the known previous image to enter maintenance.
"${COMPOSE[@]}" run --rm --no-deps --entrypoint php app artisan down --retry=60
"${COMPOSE[@]}" stop queue scheduler
bash scripts/publish-assets.sh "$APP_IMAGE" public
"${COMPOSE[@]}" up -d --no-deps --pull never app queue
# The previous release may not implement app:health. This checks its schema connection.
"${COMPOSE[@]}" exec -T app php artisan migrate:status --no-ansi
"${COMPOSE[@]}" exec -T app php artisan up
if ! curl --fail --silent --show-error --max-time 15 --retry 15 --retry-delay 2 --retry-connrefused --retry-all-errors --retry-max-time 120 "$HEALTH_URL" >/dev/null; then
    "${COMPOSE[@]}" exec -T app php artisan down --retry=60
    exit 1
fi
printf '%s\n' "$APP_IMAGE" > .release-image
echo 'Previous code and image assets restored. Database preserved. Perform login, submission and download smoke checks; review scheduler support for the selected release.'
