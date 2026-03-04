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

# Run migrations if DB is available
php artisan migrate --force 2>/dev/null || true

exec "$@"
