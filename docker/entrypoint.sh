#!/bin/sh
# ONYX AIP container entrypoint.
# Runs before php-fpm (app), queue:work (queue), or schedule:work (scheduler).

set -e

ROLE="${CONTAINER_ROLE:-app}"
echo "[onyx-aip-entrypoint] starting role=${ROLE}"

# ----------------------------------------------------------------
# 1. Storage skeleton: ensure required subdirs exist on the bind mount.
# ----------------------------------------------------------------
mkdir -p \
    /var/www/html/storage/app/private \
    /var/www/html/storage/app/public \
    /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs

# ----------------------------------------------------------------
# 2. Ownership / permissions for the bind-mounted storage.
#    Skip silently if not running as root (e.g. arbitrary uid).
# ----------------------------------------------------------------
if [ "$(id -u)" = "0" ]; then
    chown -R app:app \
        /var/www/html/storage \
        /var/www/html/bootstrap/cache
    chmod -R ug+rwX \
        /var/www/html/storage \
        /var/www/html/bootstrap/cache
fi

# ----------------------------------------------------------------
# 3. Only the `app` role bootstraps cache + optional migrations.
#    queue/scheduler just run their worker (no migrations from there).
# ----------------------------------------------------------------
if [ "$ROLE" = "app" ]; then
    # Run migrations only when explicitly opted in.
    if [ "${RUN_MIGRATIONS_ON_BOOT:-false}" = "true" ]; then
        echo "[onyx-aip-entrypoint] running migrations"
        php artisan migrate --force
    fi

    # Run seeders only when explicitly opted in. Idempotent seeders only —
    # destructive seeders should never run unattended on boot.
    if [ "${RUN_SEEDERS_ON_BOOT:-false}" = "true" ]; then
        echo "[onyx-aip-entrypoint] running seeders"
        php artisan db:seed --force
    fi

    # Abort if APP_KEY is empty — the app cannot run securely without it.
    if [ -z "${APP_KEY}" ]; then
        echo "[onyx-aip-entrypoint] ERROR: APP_KEY is not set. Run 'php artisan key:generate --show' and set it in .env." >&2
        exit 1
    fi

    echo "[onyx-aip-entrypoint] warming caches"
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
fi

echo "[onyx-aip-entrypoint] handing off to: $*"
exec "$@"
