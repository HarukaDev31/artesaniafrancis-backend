#!/bin/sh
set -e

cd /var/www/html

# APP_KEY solo en .env (generarla manualmente una vez). Nunca en .env.docker.
sed -i '/^APP_KEY=/d' .env.docker 2>/dev/null || true
# Redis Docker sin contraseña — borrar solo el valor literal "null"
sed -i '/^REDIS_PASSWORD=null$/d' .env.docker 2>/dev/null || true
sed -i '/^REDIS_PASSWORD=null$/d' .env 2>/dev/null || true

if [ ! -f .env ]; then
  if [ -f .env.docker ]; then
    cp .env.docker .env
    echo 'APP_KEY=' >> .env
  else
    echo "ERROR: cp .env.docker.example .env.docker" >&2
    exit 1
  fi
fi

if [ ! -f vendor/autoload.php ]; then
  echo "Instalando dependencias Composer..."
  composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
fi

mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

artisan() {
  env -u APP_KEY php artisan "$@"
}

echo "Verificando MySQL..."
i=0
while [ "$i" -lt 10 ]; do
  if env -u APP_KEY php docker/php/wait-db.php 2>/dev/null | grep -q OK; then
    echo "MySQL conectado."
    break
  fi
  i=$((i + 1))
  sleep 2
done

artisan migrate --force --no-interaction || echo "WARN: migrate falló" >&2

rm -f bootstrap/cache/config.php bootstrap/cache/routes-v7.php
artisan config:clear 2>/dev/null || true

if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
  echo "WARN: Genera APP_KEY: docker compose exec app php artisan key:generate --force" >&2
fi

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

echo "Iniciando PHP-FPM..."
exec "$@"
