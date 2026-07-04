#!/bin/sh
# Generar APP_KEY una sola vez (después de docker compose up).
# Uso: sh docker/fix-app-key.sh
set -e
cd "$(dirname "$0")/.."

sed -i '/^APP_KEY=/d' .env.docker

if ! grep -q '^APP_KEY=' .env 2>/dev/null; then
  echo 'APP_KEY=' >> .env
fi

docker compose exec -T app env -u APP_KEY php artisan key:generate --force

docker compose exec -T app rm -f bootstrap/cache/config.php
docker compose exec -T app env -u APP_KEY php artisan config:clear

echo "OK: $(grep '^APP_KEY=' .env)"
echo "Listo → https://api.artesaniafrancis.com/admin"
