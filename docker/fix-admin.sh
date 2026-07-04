#!/bin/sh
# Repara APP_KEY duplicada/corrupta y recrea la caché de config.
# Uso: cd backend && sh docker/fix-admin.sh
set -e

cd "$(dirname "$0")/.."

echo "==> Limpiando APP_KEY duplicadas..."
sed -i '/^APP_KEY=/d' .env.docker .env 2>/dev/null || true

echo "==> Generando una sola APP_KEY..."
docker compose exec -T app env -u APP_KEY php artisan key:generate --force

KEY_LINE=$(grep -m1 '^APP_KEY=' .env | tr -d '\r')
if [ -z "$KEY_LINE" ]; then
  echo "ERROR: no se generó APP_KEY" >&2
  exit 1
fi

sed -i '/^APP_KEY=/d' .env.docker .env
echo "$KEY_LINE" >> .env
echo "$KEY_LINE" >> .env.docker

echo "==> Líneas APP_KEY en .env: $(grep -c '^APP_KEY=' .env) (debe ser 1)"

echo "==> Recreando caché..."
docker compose exec -T app rm -f bootstrap/cache/config.php bootstrap/cache/routes-v7.php
docker compose exec -T app env -u APP_KEY php artisan optimize:clear
docker compose exec -T app env -u APP_KEY php artisan config:cache

echo "==> Verificación:"
docker compose exec -T app env -u APP_KEY php artisan config:show app.key

echo "==> Reiniciando app..."
docker compose restart app

echo "==> Listo. Prueba https://api.artesaniafrancis.com/admin"
echo "    Si sigue 500: docker compose exec app tail -30 storage/logs/laravel.log"
