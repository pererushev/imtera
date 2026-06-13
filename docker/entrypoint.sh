#!/bin/bash
set -e

if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate --force
fi

DB_HOST="${DB_HOST:-mysql}"
DB_PORT="${DB_PORT:-3306}"

echo "Waiting for MySQL at ${DB_HOST}:${DB_PORT}..."
for i in $(seq 1 60); do
    if php -r "
        try {
            new PDO(
                'mysql:host=${DB_HOST};port=${DB_PORT}',
                '${DB_USERNAME:-imtera}',
                '${DB_PASSWORD:-secret}',
                [PDO::ATTR_TIMEOUT => 2]
            );
            exit(0);
        } catch (Throwable \$e) {
            exit(1);
        }
    "; then
        echo "MySQL is ready."
        break
    fi
    if [ "$i" -eq 60 ]; then
        echo "MySQL did not become ready in time." >&2
        exit 1
    fi
    sleep 2
done

php artisan migrate --force
php artisan db:seed --force

chown -R www-data:www-data storage bootstrap/cache

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
