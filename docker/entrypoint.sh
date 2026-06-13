#!/bin/bash
set -e

if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate --force
fi

php artisan migrate --force
php artisan db:seed --force

chown -R www-data:www-data storage bootstrap/cache

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
