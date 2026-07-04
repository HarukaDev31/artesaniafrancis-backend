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

echo "Esperando MySQL..."
i=0
while [ "$i" -lt 30 ]; do
  if php -r "
    \$h = getenv('DB_HOST') ?: 'mysql';
    \$p = getenv('DB_PORT') ?: '3306';
    \$d = getenv('DB_DATABASE') ?: 'artesania_francis';
    \$u = getenv('DB_USERNAME') ?: 'artesania';
    \$pw = getenv('DB_PASSWORD') ?: 'secret';
    try {
      new PDO(\"mysql:host=\$h;port=\$p;dbname=\$d\", \$u, \$pw);
      exit(0);
    } catch (Throwable \$e) {
      exit(1);
    }
  "; then
    break
  fi
  i=$((i + 1))
  sleep 2
done

if [ "$i" -ge 30 ]; then
  echo "WARN: MySQL no respondió a tiempo; continuando de todos modos..." >&2
fi

if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
  php artisan key:generate --force
fi

php artisan migrate --force --no-interaction || echo "WARN: migrate falló — revisa logs" >&2

php artisan config:clear 2>/dev/null || true
php artisan config:cache || echo "WARN: config:cache falló" >&2
php artisan route:cache || echo "WARN: route:cache falló" >&2
php artisan view:cache || echo "WARN: view:cache falló" >&2

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

echo "Iniciando PHP-FPM..."
exec "$@"
