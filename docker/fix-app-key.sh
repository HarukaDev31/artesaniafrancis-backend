#!/bin/sh
# Arreglo definitivo APP_KEY — ejecutar en el servidor.
# Uso: cd /var/www/html/artesaniafrancis-backend && sh docker/fix-app-key.sh
set -e
cd "$(dirname "$0")/.."

echo "1. Quitando APP_KEY de .env.docker..."
sed -i '/^APP_KEY=/d' .env.docker

echo "2. Asegurando APP_KEY en .env..."
if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
  echo 'APP_KEY=' >> .env
  docker compose exec -T app env -u APP_KEY php artisan key:generate --force
fi
KEY_LINE=$(grep -m1 '^APP_KEY=base64:' .env | tr -d '\r')
sed -i '/^APP_KEY=/d' .env
echo "$KEY_LINE" >> .env

echo "3. Borrando config cache (Laravel usara .env directo)..."
docker compose exec -T app rm -f bootstrap/cache/config.php bootstrap/cache/routes-v7.php
docker compose exec -T app env -u APP_KEY php artisan config:clear

echo "4. Verificando..."
docker compose exec -T app env -u APP_KEY php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\$k = config('app.key');
if (!str_starts_with((string)\$k, 'base64:')) { echo 'FALLO: ' . var_export(\$k, true); exit(1); }
echo 'OK: ' . substr(\$k, 0, 30) . '...';
"

echo ""
echo "5. Reiniciando app..."
docker compose restart app

echo ""
echo "LISTO. Abre https://api.artesaniafrancis.com/admin"
