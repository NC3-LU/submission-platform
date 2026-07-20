#!/bin/bash
set -e

# Fix storage permissions (volume mount overrides build-time chown)
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

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
