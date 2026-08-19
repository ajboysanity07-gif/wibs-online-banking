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

if [ -d /opt/loan-document-templates ]; then
  mkdir -p storage/app/templates
  cp -rn /opt/loan-document-templates/. storage/app/templates/
  chown -R www-data:www-data storage/app/templates
fi

if [ -d /opt/loan-document-fonts ]; then
  mkdir -p storage/app/fonts
  cp -rn /opt/loan-document-fonts/. storage/app/fonts/
  chown -R www-data:www-data storage/app/fonts
fi

if [ -f .env ] && ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --force
fi

php artisan package:discover --ansi || true
php artisan storage:link || true
php artisan optimize:clear || true
php artisan optimize || true

exec "$@"