#!/bin/sh
set -e

cd /var/www/html

if [ ! -f vendor/autoload.php ]; then
  composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
fi

if [ -z "$APP_KEY" ]; then
  php artisan key:generate --force
fi

php artisan migrate --force --no-interaction

php artisan config:cache
php artisan route:cache
php artisan view:cache

chown -R www-data:www-data storage bootstrap/cache

exec "$@"
