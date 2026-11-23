#!/bin/sh
set -eu

PORT="${PORT:-10000}"
export PORT
php docker/validate-environment.php

if [ -z "${APP_URL:-}" ] && [ -n "${RENDER_EXTERNAL_URL:-}" ]; then
    APP_URL="$RENDER_EXTERNAL_URL"
    export APP_URL
fi

sed "s/__SSR_PORT__/$PORT/g" docker/apache.conf > /etc/apache2/sites-available/000-default.conf
printf 'Listen %s\n' "$PORT" > /etc/apache2/ports.conf
printf 'ServerName localhost\nServerTokens Prod\n' > /etc/apache2/conf-enabled/ssr-server.conf

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction
chown -R www-data:www-data storage bootstrap/cache
apache2ctl -t

# No database creation, migrations, seeding, or account reset during deployment.
exec docker-php-entrypoint "$@"
