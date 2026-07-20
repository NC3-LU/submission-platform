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

# Build BEFORE stopping anything.
#
# This used to run `down` first, so a build failure left the site down with no
# scripted way back: the old containers were already destroyed and the new
# image did not exist. Building first means a failed build aborts here (set -e)
# with the current site still serving, and shrinks the outage to the
# down/up window rather than the whole build.
echo "Building Docker images (existing site stays up during this)..."
docker-compose -f docker-compose.prod.yml --env-file docker-compose.env build --no-cache

# Record the data volumes so we can prove they survived the restart.
# The project name is pinned in docker-compose.prod.yml (name: applicationsnc3lu)
# rather than derived from the directory, so these names are deterministic.
# If they ever failed to resolve, compose would create EMPTY volumes and the app
# would come up looking wiped - this check turns that into a loud failure.
# Read the pinned name straight from the compose file: works regardless of
# docker-compose version, unlike `config --format json`.
PROJECT=$(awk '/^name:[[:space:]]/ {print $2; exit}' docker-compose.prod.yml)
if [ -z "$PROJECT" ]; then
    echo "❌ Could not read the pinned project name from docker-compose.prod.yml."
    echo "   Refusing to guess: a wrong project name resolves to EMPTY volumes."
    exit 1
fi
echo "Compose project: $PROJECT"
DB_VOL="${PROJECT}_dbdata"
ST_VOL="${PROJECT}_storage_data"
echo "Expecting data volumes: $DB_VOL, $ST_VOL"
for V in "$DB_VOL" "$ST_VOL"; do
    if ! docker volume inspect "$V" >/dev/null 2>&1; then
        echo "❌ Expected data volume '$V' does not exist before deploy. Aborting."
        echo "   Existing volumes:"; docker volume ls
        exit 1
    fi
done
# Count only uploaded submission files. The storage volume also holds
# framework caches (compiled Blade views, sessions) which are ephemeral and
# legitimately shrink when caches are rebuilt - counting those made this check
# abort a deploy in which no user data had been lost at all.
PRE_STORAGE_FILES=$(docker run --rm -v "$ST_VOL":/data:ro alpine:3.20 sh -c 'find /data -path "*submissions*" -type f | wc -l' 2>/dev/null || echo 0)
echo "✅ Data volumes present ($PRE_STORAGE_FILES uploaded submission files)"

# Stop existing containers (graceful).
# NOTE: no -v / --volumes. Named volumes (dbdata, storage_data) must survive;
# they hold the database and every uploaded submission file.
echo "Stopping existing containers..."
docker-compose -f docker-compose.prod.yml --env-file docker-compose.env down || true

# Prove `down` did not take the volumes with it.
for V in "$DB_VOL" "$ST_VOL"; do
    if ! docker volume inspect "$V" >/dev/null 2>&1; then
        echo "❌ Data volume '$V' disappeared during 'down'. STOPPING - restore from backup."
        exit 1
    fi
done
echo "✅ Data volumes intact after down"

# Start database first
echo "Starting database service..."
docker-compose -f docker-compose.prod.yml --env-file docker-compose.env up -d db

# Wait for database to be healthy
echo "Waiting for database to be ready..."
timeout=300
counter=0
while ! docker-compose -f docker-compose.prod.yml --env-file docker-compose.env exec -T db mysqladmin ping -h localhost --silent; do
    echo "Waiting for database connection... ($counter/60)"
    sleep 5
    counter=$((counter + 1))
    if [ $counter -gt 60 ]; then
        echo "❌ Database failed to start within timeout period"
        docker-compose -f docker-compose.prod.yml --env-file docker-compose.env logs db
        exit 1
    fi
done

echo "✅ Database is ready!"

# Start app service
echo "Starting application service..."
docker-compose -f docker-compose.prod.yml --env-file docker-compose.env up -d app

# Wait for app to be ready
echo "Waiting for application to be ready..."
sleep 30

# Test database connectivity from app
echo "Testing database connectivity from application..."
if ! docker-compose -f docker-compose.prod.yml --env-file docker-compose.env exec -T app php -r "
try {
    \$pdo = new PDO('mysql:host=db;dbname=${DB_DATABASE}', '${DB_USERNAME}', '${DB_PASSWORD}');
    echo 'Database connection successful\n';
} catch (Exception \$e) {
    echo 'Database connection failed: ' . \$e->getMessage() . \"\n\";
    exit(1);
}"; then
    echo "❌ Database connectivity test failed"
    echo "App container logs:"
    docker-compose -f docker-compose.prod.yml --env-file docker-compose.env logs app
    echo "Database container logs:"
    docker-compose -f docker-compose.prod.yml --env-file docker-compose.env logs db
    exit 1
fi

echo "✅ Database connectivity test passed!"

# Run database migrations
echo "Running database migrations..."
if ! docker-compose -f docker-compose.prod.yml --env-file docker-compose.env exec -T app php artisan migrate --force; then
    echo "❌ Migration failed. Checking logs..."
    docker-compose -f docker-compose.prod.yml --env-file docker-compose.env exec -T app cat storage/logs/laravel.log 2>/dev/null || echo "No Laravel logs available"
    echo "App container logs:"
    docker-compose -f docker-compose.prod.yml --env-file docker-compose.env logs app
    exit 1
fi

# Data sanity check: a fresh/empty volume would migrate cleanly and look like a
# successful deploy while actually having lost everything. Compare against what
# was there before.
echo "Verifying data survived the deploy..."
POST_STORAGE_FILES=$(docker run --rm -v "$ST_VOL":/data:ro alpine:3.20 sh -c 'find /data -path "*submissions*" -type f | wc -l' 2>/dev/null || echo 0)
POST_USERS=$(docker-compose -f docker-compose.prod.yml --env-file docker-compose.env exec -T app \
    php -r '$p=new PDO("mysql:host=db;dbname='"${DB_DATABASE}"'","'"${DB_USERNAME}"'","'"${DB_PASSWORD}"'");echo $p->query("SELECT COUNT(*) FROM users")->fetchColumn();' 2>/dev/null || echo 0)
echo "   uploaded submission files: $PRE_STORAGE_FILES before -> $POST_STORAGE_FILES after"
echo "   users in database: $POST_USERS"
if [ "$POST_STORAGE_FILES" -lt "$PRE_STORAGE_FILES" ]; then
    echo "❌ Uploaded files are missing after deploy ($PRE_STORAGE_FILES -> $POST_STORAGE_FILES). Restore from backup."
    exit 1
fi
if [ "${POST_USERS:-0}" -lt 1 ]; then
    echo "❌ Database has no users after deploy - it is very likely an empty volume. Restore from backup."
    exit 1
fi
echo "✅ Data intact"

echo "Clearing caches..."
docker-compose -f docker-compose.prod.yml --env-file docker-compose.env exec -T app php artisan config:clear
docker-compose -f docker-compose.prod.yml --env-file docker-compose.env exec -T app php artisan cache:clear
docker-compose -f docker-compose.prod.yml --env-file docker-compose.env exec -T app php artisan view:clear
docker-compose -f docker-compose.prod.yml --env-file docker-compose.env exec -T app php artisan route:clear

echo "Optimizing application..."
docker-compose -f docker-compose.prod.yml --env-file docker-compose.env exec -T app php artisan config:cache
docker-compose -f docker-compose.prod.yml --env-file docker-compose.env exec -T app php artisan route:cache
docker-compose -f docker-compose.prod.yml --env-file docker-compose.env exec -T app php artisan view:cache

# Start the queue worker. QUEUE_CONNECTION=database, so without this process
# queued jobs sit in the jobs table forever. That matters most for
# ScanSubmissionFileJob: downloads are gated fail-closed on a completed scan,
# so an unprocessed queue makes every newly uploaded file undownloadable.
# 'docker-compose down' at the start of this script removes the container, so
# 'restart: unless-stopped' does not bring it back on its own.
echo "Starting queue worker..."
docker-compose -f docker-compose.prod.yml --env-file docker-compose.env up -d queue

# Fail loudly rather than leaving a silently broken scanning pipeline.
sleep 5
if [ -z "$(docker-compose -f docker-compose.prod.yml --env-file docker-compose.env ps -q queue)" ] || \
   [ "$(docker inspect -f '{{.State.Running}}' "$(docker-compose -f docker-compose.prod.yml --env-file docker-compose.env ps -q queue)" 2>/dev/null)" != "true" ]; then
    echo "❌ Queue worker failed to start. Queued jobs will not be processed."
    docker-compose -f docker-compose.prod.yml --env-file docker-compose.env logs queue
    exit 1
fi
echo "✅ Queue worker running."

echo "✅ Deployment completed successfully!"

# Show final status
echo "Final container status:"
docker-compose -f docker-compose.prod.yml --env-file docker-compose.env ps