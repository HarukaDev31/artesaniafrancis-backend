#!/bin/sh
# Diagnóstico rápido del 500 en /admin
set -e
cd "$(dirname "$0")/.."

echo "=== APP_KEY en .env ==="
grep -c '^APP_KEY=base64:' .env 2>/dev/null || echo "0"
grep '^APP_KEY=' .env 2>/dev/null | head -1 || echo "(ninguna)"

echo ""
echo "=== APP_KEY en .env.docker (debe estar vacío / sin línea) ==="
grep '^APP_KEY=' .env.docker 2>/dev/null || echo "(ninguna — correcto)"

echo ""
echo "=== app.key como la ve PHP-FPM (sin env -u) ==="
docker compose exec -T app php artisan config:show app.key 2>/dev/null || true

echo ""
echo "=== app.key ignorando Docker (env -u APP_KEY) ==="
docker compose exec -T app env -u APP_KEY php artisan config:show app.key 2>/dev/null || true

echo ""
echo "=== Clave en bootstrap/cache/config.php ==="
docker compose exec -T app php -r "
if (! is_file('bootstrap/cache/config.php')) { echo '(sin config cache)'; exit; }
\$c = require 'bootstrap/cache/config.php';
echo \$c['app']['key'] ?? 'MISSING';
" 2>/dev/null || echo "FALLÓ"

echo ""
echo "=== Redis ==="
docker compose exec -T app php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo Illuminate\Support\Facades\Redis::ping();
" 2>/dev/null || echo "FALLÓ"

echo ""
echo "=== Rutas admin ==="
docker compose exec -T app env -u APP_KEY php artisan route:list --path=admin 2>&1 | head -15

echo ""
echo "=== Últimos errores Laravel ==="
docker compose exec -T app sh -c 'grep "production.ERROR" storage/logs/laravel.log 2>/dev/null | tail -3' || echo "(sin log)"

echo ""
echo "=== Simular GET /admin/login ==="
docker compose exec -T app env -u APP_KEY php artisan tinker --execute="echo route('filament.admin.auth.login');" 2>&1 || true
