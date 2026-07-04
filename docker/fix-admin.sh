#!/bin/sh
# Repara APP_KEY y recrea config cache (obligatorio: env -u APP_KEY).
# Uso: cd backend && sh docker/fix-admin.sh
set -e

cd "$(dirname "$0")/.."

echo "==> Limpiando APP_KEY duplicadas o vacías..."
sed -i '/^APP_KEY=/d' .env.docker .env 2>/dev/null || true

echo "==> Generando una sola APP_KEY..."
echo 'APP_KEY=' >> .env
docker compose exec -T app env -u APP_KEY php artisan key:generate --force

KEY_LINE=$(grep -m1 '^APP_KEY=base64:' .env | tr -d '\r')
if [ -z "$KEY_LINE" ]; then
  echo "ERROR: no se generó APP_KEY" >&2
  exit 1
fi

sed -i '/^APP_KEY=/d' .env.docker .env
echo "$KEY_LINE" >> .env
echo "$KEY_LINE" >> .env.docker

echo "==> Líneas APP_KEY en .env: $(grep -c '^APP_KEY=' .env) (debe ser 1)"

echo "==> Recreando caché (sin APP_KEY vacía de Docker)..."
docker compose exec -T app rm -f bootstrap/cache/config.php bootstrap/cache/routes-v7.php
docker compose exec -T app env -u APP_KEY php artisan optimize:clear
docker compose exec -T app env -u APP_KEY php artisan config:cache

CACHED_KEY=$(docker compose exec -T app php -r "
if (! is_file('bootstrap/cache/config.php')) { exit(1); }
\$c = require 'bootstrap/cache/config.php';
echo \$c['app']['key'] ?? '';
" | tr -d '\r')

if [ -z "$CACHED_KEY" ]; then
  echo "ERROR: bootstrap/cache/config.php sigue sin APP_KEY" >&2
  echo "  Ejecuta: docker compose exec app env -u APP_KEY php artisan config:cache" >&2
  exit 1
fi

echo "==> APP_KEY en config cache: OK (${CACHED_KEY:0:20}...)"

echo "==> Recreando contenedor (carga APP_KEY real desde .env.docker)..."
docker compose up -d --force-recreate app

echo "==> Listo. Prueba https://api.artesaniafrancis.com/admin"
echo "    Usuario: docker compose exec -it app php artisan make:filament-user"
