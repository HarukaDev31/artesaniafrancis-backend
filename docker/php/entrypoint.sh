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

# El volumen monta el .env del host (sqlite/local). En Docker usamos .env.docker.
if [ -f .env.docker ]; then
  dedupe_app_key .env.docker
  sed -i '/^APP_KEY=$/d' .env.docker 2>/dev/null || true
  cp .env.docker .env
elif [ ! -f .env ]; then
  echo "ERROR: crea .env.docker (cp .env.docker.example .env.docker)" >&2
  exit 1
fi

dedupe_app_key .env
sed -i '/^APP_KEY=$/d' .env 2>/dev/null || true

if [ ! -f vendor/autoload.php ]; then
  echo "Instalando dependencias Composer..."
  composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
fi

mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# Artisan debe leer APP_KEY del .env, no del entorno Docker (evita APP_KEY= vacío)
artisan() {
  env -u APP_KEY php artisan "$@"
}

app_key_from_env() {
  grep -m1 '^APP_KEY=base64:' .env 2>/dev/null | cut -d= -f2- | tr -d '\r'
}

APP_KEY_VALUE=$(app_key_from_env)
if [ -z "$APP_KEY_VALUE" ]; then
  echo "Generando APP_KEY..."
  grep -q '^APP_KEY=' .env || echo 'APP_KEY=' >> .env
  artisan key:generate --force
  dedupe_app_key .env
  APP_KEY_VALUE=$(app_key_from_env)
fi

# Una sola APP_KEY en .env.docker
if [ -f .env.docker ] && [ -n "$APP_KEY_VALUE" ]; then
  sed -i '/^APP_KEY=/d' .env.docker
  echo "APP_KEY=${APP_KEY_VALUE}" >> .env.docker
  cp .env.docker .env
fi

echo "Verificando MySQL..."
i=0
while [ "$i" -lt 10 ]; do
  if env -u APP_KEY php docker/php/wait-db.php 2>/dev/null | grep -q OK; then
    echo "MySQL conectado."
    break
  fi
  i=$((i + 1))
  echo "  intento $i/10:"
  env -u APP_KEY php docker/php/wait-db.php 2>&1 || true
  sleep 2
done

if [ "$i" -ge 10 ]; then
  echo "WARN: no se pudo conectar a MySQL." >&2
fi

artisan migrate --force --no-interaction || echo "WARN: migrate falló — revisa logs" >&2

artisan optimize:clear 2>/dev/null || true
artisan config:cache || echo "WARN: config:cache falló" >&2

if ! grep -q 'base64:' bootstrap/cache/config.php 2>/dev/null; then
  echo "ERROR: config cache sin APP_KEY, reintentando..." >&2
  rm -f bootstrap/cache/config.php
  artisan config:cache
fi

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

echo "Iniciando PHP-FPM..."
exec "$@"
