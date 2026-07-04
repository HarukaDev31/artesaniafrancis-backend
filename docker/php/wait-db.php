<?php

/**
 * Comprueba la conexión MySQL usando la configuración de Laravel (.env).
 * Uso: php docker/php/wait-db.php
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    Illuminate\Support\Facades\DB::connection()->getPdo();
    fwrite(STDOUT, "OK\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
