#!/bin/sh
# Repara /admin (APP_KEY + caché). Delega en fix-app-key.sh.
# Uso: cd backend && sh docker/fix-admin.sh
set -e

cd "$(dirname "$0")/.."

sh docker/fix-app-key.sh

echo "==> Listo. Prueba https://api.artesaniafrancis.com/admin"
echo "    Usuario: docker compose exec -it app php artisan make:filament-user"
