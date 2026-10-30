#!/bin/sh
set -e

cd /var/www/html

mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    database/data

touch database/data/database.sqlite

if [ ! -s database/data/database.sqlite ] && [ -s database/database.sqlite ]; then
    cp database/database.sqlite database/data/database.sqlite
fi

chown -R www-data:www-data storage bootstrap/cache database/data

php artisan package:discover --ansi
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec supervisord -c /etc/supervisord.conf
