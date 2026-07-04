<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CORS — Casa Sagrada
    |--------------------------------------------------------------------------
    | Restringimos los orígenes permitidos al frontend Astro únicamente.
    | NUNCA usar ['*'] en producción.
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_filter([
        env('FRONTEND_URL', 'http://localhost:4321'),
        env('FRONTEND_URL_PROD'),
        env('FRONTEND_URL_NETLIFY'), // ej: https://tu-sitio.netlify.app (previews)
    ]),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
