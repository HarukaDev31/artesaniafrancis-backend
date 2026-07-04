<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Casa Sagrada
|--------------------------------------------------------------------------
|
| Endpoints públicos con rate limiting estricto para evitar abuso.
| Las rutas autenticadas (clientes, pedidos) van bajo middleware('auth:sanctum').
|
*/

// PÚBLICAS (solo lectura) con throttle 60/min por IP
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{slug}', [ProductController::class, 'show'])
        ->where('slug', '[a-z0-9-]+'); // whitelist regex slug
    Route::get('/categories', [CategoryController::class, 'index']);
});

// AUTENTICADAS (para fase 2 — auth de clientes, pedidos, etc.)
Route::middleware(['auth:sanctum', 'throttle:30,1'])->group(function () {
    // Route::post('/orders', [OrderController::class, 'store']);
    // Route::get('/orders', [OrderController::class, 'index']);
});
