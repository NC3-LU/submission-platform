#!/usr/bin/env bash
set -euo pipefail
browser_root=$(mktemp -d /tmp/submission-browser.XXXXXX)
export APP_ENV=testing APP_DEBUG=false DB_CONNECTION=sqlite DB_DATABASE="$browser_root/browser.sqlite"
export APP_KEY=base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=
export APP_URL=http://127.0.0.1:8767 CACHE_STORE=file SESSION_DRIVER=file SESSION_SECURE_COOKIE=false
export MAIL_MAILER=log QUEUE_CONNECTION=database PANDORA_ENABLED=true PANDORA_BLOCK_MALICIOUS=false WEBHOOKS_ENABLED=false TRUSTED_HOSTS=127.0.0.1 TRUSTED_PROXIES=
export LARAVEL_STORAGE_PATH="$browser_root/storage"
export APP_CONFIG_CACHE="$browser_root/config.php" APP_ROUTES_CACHE="$browser_root/routes.php" APP_EVENTS_CACHE="$browser_root/events.php"
export APP_SERVICES_CACHE="$browser_root/services.php" APP_PACKAGES_CACHE="$browser_root/packages.php"
mkdir -p "$browser_root/storage/app/private" "$browser_root/storage/app/public" "$browser_root/storage/logs" "$browser_root/storage/framework/cache/data" "$browser_root/storage/framework/sessions" "$browser_root/storage/framework/views" .browser
touch "$DB_DATABASE"
php artisan migrate --force >/dev/null
php tests/Browser/seed.php > .browser/fixture.json
(cd public && php -S 127.0.0.1:8767 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php) > "$browser_root/server.log" 2>&1 &
browser_pid=$!
trap 'kill "$browser_pid" 2>/dev/null || true; cp "$browser_root/server.log" .browser/server.log; rm -rf "$browser_root"' EXIT INT TERM
wait "$browser_pid"
