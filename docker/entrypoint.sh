#!/bin/bash
set -e

# Clear and rebuild caches on every container start
# This ensures fresh deploys don't serve stale config/views
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Run migrations if DB is available
php artisan migrate --force 2>/dev/null || true

exec "$@"
