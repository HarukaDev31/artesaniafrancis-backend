#!/bin/sh
set -e

cd /var/www/html

dedupe_app_key() {
  _file="$1"
  [ -f "$_file" ] || return 0
  _line=$(grep -m1 '^APP_KEY=base64:' "$_file" 2>/dev/null || true)
  sed -i '/^APP_KEY=/d' "$_file"
  if [ -n "$_line" ]; then
    echo "$_line" >> "$_file"
  fi
}

# APP_KEY NUNCA va en .env.docker — Docker la inyecta y anula el .env
if [ -f .env.docker ]; then
  sed -i '/^APP_KEY=/d' .env.docker
  _saved_key=$(grep -m1 '^APP_KEY=base64:' .env 2>/dev/null || true)
  cp .env.docker .env
  if [ -n "$_saved_key" ]; then
    sed -i '/^APP_KEY=/d' .env
    echo "$_saved_key" >> .env
  fi
elif [ ! -f .env ]; then
  echo "ERROR: crea .env.docker (cp .env.docker.example .env.docker)" >&2
  exit 1
fi

dedupe_app_key .env

if [ ! -f vendor/autoload.php ]; then
  echo "Instalando dependencias Composer..."
  composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
fi

mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

artisan() {
  env -u APP_KEY php artisan "$@"
}

app_key_from_env() {
  grep -m1 '^APP_KEY=base64:' .env 2>/dev/null | cut -d= -f2- | tr -d '\r'
}

APP_KEY_VALUE=$(app_key_from_env)
if [ -z "$APP_KEY_VALUE" ]; then
  echo "Generando APP_KEY..."
  echo 'APP_KEY=' >> .env
  artisan key:generate --force
  dedupe_app_key .env
  APP_KEY_VALUE=$(app_key_from_env)
fi

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

artisan optimize:clear 2>/dev/null || true
artisan config:cache || echo "WARN: config:cache falló" >&2

if ! grep -q 'base64:' bootstrap/cache/config.php 2>/dev/null; then
  rm -f bootstrap/cache/config.php
  artisan config:cache
fi

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

echo "Iniciando PHP-FPM..."
exec "$@"
