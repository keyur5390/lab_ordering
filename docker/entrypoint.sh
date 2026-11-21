#!/bin/sh
# Container start (B-33).
# - With a command (docker compose run --rm app php artisan migrate), runs just that, as www-data.
# - Otherwise caches config, routes, views and events, migrates when RUN_MIGRATIONS=true (web
#   container only), then runs the role's process. CONTAINER_ROLE: app (nginx + PHP-FPM), queue or
#   scheduler.
set -e
cd /var/www/html

# Volumes start empty and root-owned: make the folders Laravel writes to belong to www-data.
mkdir -p storage/app/private storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
chown -R www-data:www-data storage bootstrap/cache

if [ "$#" -gt 0 ]; then
    exec runuser -u www-data -- "$@"
fi

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not set. Generate one with: docker compose run --rm app php artisan key:generate --show" >&2
    exit 1
fi

runuser -u www-data -- php artisan optimize

if [ "$CONTAINER_ROLE" = "app" ] && [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    runuser -u www-data -- php artisan migrate --force
fi

case "$CONTAINER_ROLE" in
    app)
        exec supervisord -c /etc/supervisor/supervisord.conf
        ;;
    queue)
        exec runuser -u www-data -- php artisan queue:work --sleep=3 --tries=3 --backoff=60 --max-time=3600
        ;;
    scheduler)
        exec runuser -u www-data -- php artisan schedule:work
        ;;
    *)
        echo "Unknown CONTAINER_ROLE: $CONTAINER_ROLE (use app, queue or scheduler)" >&2
        exit 1
        ;;
esac
