#!/bin/sh
# Generar APP_KEY en .env (nunca en .env.docker) y recrear el contenedor app.
# Uso: sh docker/fix-app-key.sh
set -e
cd "$(dirname "$0")/.."

echo "==> Quitando APP_KEY de .env.docker (solo debe vivir en .env)..."
sed -i '/^APP_KEY=/d' .env.docker

if ! grep -q '^APP_KEY=' .env 2>/dev/null; then
  echo 'APP_KEY=' >> .env
fi

docker compose exec -T app env -u APP_KEY php artisan key:generate --force

docker compose exec -T app rm -f bootstrap/cache/config.php bootstrap/cache/routes-v7.php
docker compose exec -T app env -u APP_KEY php artisan config:clear

echo "==> Recreando app (inyecta APP_KEY desde .env vía docker-compose)..."
docker compose up -d --force-recreate app

sleep 3
KEY=$(docker compose exec -T app php artisan config:show app.key 2>/dev/null | tail -1 | tr -d '\r' || true)
if [ -z "$KEY" ] || [ "$KEY" = "null" ]; then
  echo "ERROR: APP_KEY sigue vacía en el contenedor. Revisa que .env tenga APP_KEY=base64:..." >&2
  exit 1
fi

echo "OK: $(grep '^APP_KEY=' .env)"
echo "Listo → https://api.artesaniafrancis.com/admin"
