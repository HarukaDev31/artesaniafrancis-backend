#!/bin/sh
set -e

cd /var/www/html

# El volumen monta el .env del host (sqlite/local). En Docker usamos .env.docker.
if [ -f .env.docker ]; then
  cp .env.docker .env
elif [ ! -f .env ]; then
  echo "ERROR: crea .env.docker (cp .env.docker.example .env.docker)" >&2
  exit 1
fi

# Quitar APP_KEY= vacío que Docker inyecta vía env_file y anula el .env
sed -i '/^APP_KEY=$/d' .env.docker 2>/dev/null || true
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
  grep '^APP_KEY=' .env 2>/dev/null | cut -d= -f2- | tr -d '\r'
}

APP_KEY_VALUE=$(app_key_from_env)
if [ -z "$APP_KEY_VALUE" ] || [ "$APP_KEY_VALUE" = "base64:" ]; then
  echo "Generando APP_KEY..."
  artisan key:generate --force
  APP_KEY_VALUE=$(app_key_from_env)
fi

# Persistir en .env.docker para que sobreviva reinicios
if [ -f .env.docker ] && [ -n "$APP_KEY_VALUE" ]; then
  if grep -q '^APP_KEY=' .env.docker; then
    sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY_VALUE}|" .env.docker
  else
    echo "APP_KEY=${APP_KEY_VALUE}" >> .env.docker
  fi
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
