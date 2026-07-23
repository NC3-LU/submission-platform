#!/bin/bash
set -e

# Fix storage permissions (volume mount overrides build-time chown)
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

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
    chown -R www-data:www-data /var/www/html/public/storage
    chmod -R 775 /var/www/html/public/storage
else
    php artisan storage:link --force
fi

# Clear and rebuild caches on every container start
# This ensures fresh deploys don't serve stale config/views
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Run migrations. Only one container may do this: app and queue share this
# entrypoint, and Laravel takes no migration lock, so letting both run
# concurrently against the same database can double-apply or deadlock.
# The queue service sets RUN_MIGRATIONS=false for exactly this reason.
#
# Failures are reported rather than swallowed. Previously this was
# `2>/dev/null || true`, which hid a broken migration behind a zero exit code.
# It stays non-fatal so a transient DB hiccup does not wedge the container in a
# restart loop, but the error is now visible in the logs.
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "Running database migrations..."
    php artisan migrate --force || echo "⚠️  MIGRATION FAILED — see output above. Application may be running against an outdated schema."
else
    echo "Skipping migrations (RUN_MIGRATIONS=${RUN_MIGRATIONS})."
fi

exec "$@"
