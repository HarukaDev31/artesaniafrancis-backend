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

if [ ! -f vendor/autoload.php ]; then
  echo "Instalando dependencias Composer..."
  composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
fi

mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
  echo "Generando APP_KEY..."
  php artisan key:generate --force
fi

# MySQL ya está healthy por depends_on; reintentos cortos con diagnóstico.
echo "Verificando MySQL..."
i=0
while [ "$i" -lt 10 ]; do
  if php docker/php/wait-db.php 2>/dev/null | grep -q OK; then
    echo "MySQL conectado."
    break
  fi
  i=$((i + 1))
  echo "  intento $i/10:"
  php docker/php/wait-db.php 2>&1 || true
  sleep 2
done

if [ "$i" -ge 10 ]; then
  echo "WARN: no se pudo conectar a MySQL." >&2
  echo "  → DB_PASSWORD en .env.docker debe coincidir con MYSQL_PASSWORD" >&2
  echo "  → Si cambiaste la contraseña, borra el volumen: docker compose down -v" >&2
fi

php artisan migrate --force --no-interaction || echo "WARN: migrate falló — revisa logs" >&2

php artisan config:clear 2>/dev/null || true
php artisan config:cache || echo "WARN: config:cache falló" >&2
php artisan route:cache || echo "WARN: route:cache falló" >&2
php artisan view:cache || echo "WARN: view:cache falló" >&2

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

echo "Iniciando PHP-FPM..."
exec "$@"
