#!/bin/bash
set -e

echo "🚀 Starting deployment process..."


# Source the environment variables from docker-compose.env
if [ -f docker-compose.env ]; then
    echo "Loading environment variables from docker-compose.env..."
    set -a
    source docker-compose.env
    set +a
    echo "✅ Environment variables loaded"
else
    echo "❌ docker-compose.env file not found"
    exit 1
fi

if [ -n "$PROXY" ]; then
    export HTTP_PROXY="$PROXY"
    export HTTPS_PROXY="$PROXY"
    export http_proxy="$PROXY"
    export https_proxy="$PROXY"
fi
# Verify required variables are loaded
required_vars=("DB_DATABASE" "DB_USERNAME" "DB_PASSWORD" "MYSQL_ROOT_PASSWORD")
for var in "${required_vars[@]}"; do
    if [ -z "${!var}" ]; then
        echo "❌ Required variable $var is not set"
        exit 1
    else
        echo "✅ Variable $var is set"
    fi
done

# Stop existing containers (graceful)
echo "Stopping existing containers..."
docker-compose --env-file docker-compose.env down || true

# Build Docker images
echo "Building Docker images..."
docker-compose --env-file docker-compose.env build --no-cache

# Start database first
echo "Starting database service..."
docker-compose --env-file docker-compose.env up -d db

# Wait for database to be healthy
echo "Waiting for database to be ready..."
timeout=300
counter=0
while ! docker-compose --env-file docker-compose.env exec -T db mysqladmin ping -h localhost --silent; do
    echo "Waiting for database connection... ($counter/60)"
    sleep 5
    counter=$((counter + 1))
    if [ $counter -gt 60 ]; then
        echo "❌ Database failed to start within timeout period"
        docker-compose --env-file docker-compose.env logs db
        exit 1
    fi
done

echo "✅ Database is ready!"

# Start app service
echo "Starting application service..."
docker-compose --env-file docker-compose.env up -d app

# Wait for app to be ready
echo "Waiting for application to be ready..."
sleep 30

# Test database connectivity from app
echo "Testing database connectivity from application..."
if ! docker-compose --env-file docker-compose.env exec -T app php -r "
try {
    \$pdo = new PDO('mysql:host=db;dbname=${DB_DATABASE}', '${DB_USERNAME}', '${DB_PASSWORD}');
    echo 'Database connection successful\n';
} catch (Exception \$e) {
    echo 'Database connection failed: ' . \$e->getMessage() . \"\n\";
    exit(1);
}"; then
    echo "❌ Database connectivity test failed"
    echo "App container logs:"
    docker-compose --env-file docker-compose.env logs app
    echo "Database container logs:"
    docker-compose --env-file docker-compose.env logs db
    exit 1
fi

echo "✅ Database connectivity test passed!"

# Run database migrations
echo "Running database migrations..."
if ! docker-compose --env-file docker-compose.env exec -T app php artisan migrate --force; then
    echo "❌ Migration failed. Checking logs..."
    docker-compose --env-file docker-compose.env exec -T app cat storage/logs/laravel.log 2>/dev/null || echo "No Laravel logs available"
    echo "App container logs:"
    docker-compose --env-file docker-compose.env logs app
    exit 1
fi

echo "Clearing caches..."
docker-compose --env-file docker-compose.env exec -T app php artisan config:clear
docker-compose --env-file docker-compose.env exec -T app php artisan cache:clear
docker-compose --env-file docker-compose.env exec -T app php artisan view:clear
docker-compose --env-file docker-compose.env exec -T app php artisan route:clear

echo "Optimizing application..."
docker-compose --env-file docker-compose.env exec -T app php artisan config:cache
docker-compose --env-file docker-compose.env exec -T app php artisan route:cache
docker-compose --env-file docker-compose.env exec -T app php artisan view:cache

# Start the queue worker. QUEUE_CONNECTION=database, so without this process
# queued jobs sit in the jobs table forever. That matters most for
# ScanSubmissionFileJob: downloads are gated fail-closed on a completed scan,
# so an unprocessed queue makes every newly uploaded file undownloadable.
# 'docker-compose down' at the start of this script removes the container, so
# 'restart: unless-stopped' does not bring it back on its own.
echo "Starting queue worker..."
docker-compose --env-file docker-compose.env up -d queue

# Fail loudly rather than leaving a silently broken scanning pipeline.
sleep 5
if [ -z "$(docker-compose --env-file docker-compose.env ps -q queue)" ] || \
   [ "$(docker inspect -f '{{.State.Running}}' "$(docker-compose --env-file docker-compose.env ps -q queue)" 2>/dev/null)" != "true" ]; then
    echo "❌ Queue worker failed to start. Queued jobs will not be processed."
    docker-compose --env-file docker-compose.env logs queue
    exit 1
fi
echo "✅ Queue worker running."

echo "✅ Deployment completed successfully!"

# Show final status
echo "Final container status:"
docker-compose --env-file docker-compose.env ps