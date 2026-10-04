#!/bin/bash
set -e

cd /app

# Railway injects $PORT; default to 8000 for local docker runs.
PORT=${PORT:-8000}

# Ensure writable dirs exist (fresh container)
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
         storage/app/private storage/app/public storage/logs bootstrap/cache

# Cache config/routes now that production env vars are present
php artisan config:cache
php artisan route:cache

# Run migrations + seeders. Retry while the Railway MySQL service is starting.
for i in $(seq 1 24); do
    if php artisan migrate --force --seed; then
        break
    fi
    if [ "$i" = "24" ]; then
        echo "Migrations failed after 24 attempts." >&2
        exit 1
    fi
    echo "Database not ready yet, retrying in 5s... ($i/24)"
    sleep 5
done

# Public storage symlink (deposit screenshots, etc.)
php artisan storage:link 2>/dev/null || true

# Real-time game server (runs in background; game UI falls back to polling)
php artisan reverb:start --host=0.0.0.0 --port=8081 >> storage/logs/reverb.log 2>&1 &
echo "Reverb starting in background (port 8081)."

exec php artisan serve --host=0.0.0.0 --port="$PORT"
