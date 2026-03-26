#!/usr/bin/env bash
set -e

cd /var/www/html

mkdir -p \
  storage/framework/cache \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

if [ -f .env ] && ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --force
fi

php artisan package:discover --ansi || true
php artisan storage:link || true
php artisan optimize:clear || true
php artisan optimize || true

exec "$@"