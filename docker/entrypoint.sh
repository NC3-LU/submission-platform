#!/bin/sh
set -e

# Copy public files to the shared volume so nginx can serve them
cp -r /var/www/html/public/. /var/www/html/public-shared/

# Run migrations if DB is ready
php artisan migrate --force 2>/dev/null || true

# Cache config and routes for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
