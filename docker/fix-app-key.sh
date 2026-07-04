#!/bin/sh
# Arreglo definitivo APP_KEY — ejecutar UNA sola vez en el servidor.
# Uso: cd /var/www/html/artesaniafrancis-backend && sh docker/fix-app-key.sh
set -e
cd "$(dirname "$0")/.."

echo "1. Quitando APP_KEY de .env.docker (Docker la inyectaba vacia)..."
sed -i '/^APP_KEY=/d' .env.docker

echo "2. Asegurando APP_KEY en .env..."
if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
  echo 'APP_KEY=' >> .env
  docker compose exec -T app env -u APP_KEY php artisan key:generate --force
fi
KEY_LINE=$(grep -m1 '^APP_KEY=base64:' .env | tr -d '\r')
sed -i '/^APP_KEY=/d' .env
echo "$KEY_LINE" >> .env

echo "3. Recreando config cache..."
docker compose exec -T app rm -f bootstrap/cache/config.php
docker compose exec -T app env -u APP_KEY php artisan config:cache

echo "4. Verificando..."
docker compose exec -T app php -r "
\$c = require 'bootstrap/cache/config.php';
\$k = \$c['app']['key'] ?? '';
if (!str_starts_with(\$k, 'base64:')) { echo 'FALLO: sin clave'; exit(1); }
echo 'OK: ' . substr(\$k, 0, 30) . '...';
"

echo ""
echo "5. Recreando contenedor (sin APP_KEY en entorno Docker)..."
docker compose up -d --force-recreate app

echo ""
echo "LISTO. Abre https://api.artesaniafrancis.com/admin"
