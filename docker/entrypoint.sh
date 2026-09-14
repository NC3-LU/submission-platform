#!/bin/bash
set -e

mkdir -p storage/app/private storage/app/public storage/framework/sessions storage/framework/views storage/framework/cache/data storage/logs bootstrap/cache

# Recreate the public/storage symlink on every start. public/ lives in the
# image layer (not the persistent storage volume), so a fresh deploy ships
# without the link and uploaded files under storage/app/public — form header
# images, etc. — 404 until it is relinked. --force makes this idempotent.
#
# Production is the exception: docker-compose.prod.yml bind-mounts the host's
# public/storage onto storage/app/public so the HOST Apache can serve those
# files. There public/storage is a real directory, and storage:link --force
# would fail on it (delete() cannot remove a directory, then symlink() errors
# "File exists") — which, under `set -e`, wedges the container in a restart
# loop. Skip it whenever the path is already a directory rather than a link.
if [ -d /var/www/html/public/storage ] && [ ! -L /var/www/html/public/storage ]; then
    echo "public/storage is a real directory (bind mount) — skipping storage:link."
else
    php artisan storage:link --force
fi

# Stop startup on a schema failure. Queue and scheduler containers never migrate.
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan app:health

# CLI initialization runs as root; hand its generated files to the runtime user.
chown -R www-data:www-data storage bootstrap/cache
find storage bootstrap/cache -type d -exec chmod 770 {} +
find storage bootstrap/cache -type f -exec chmod 660 {} +
find storage/app/public -type d -exec chmod 755 {} +
find storage/app/public -type f -exec chmod 644 {} +

if [ "$1" = "php" ]; then
    if command -v su-exec >/dev/null; then exec su-exec www-data "$@"; fi
    exec gosu www-data "$@"
fi
exec "$@"
