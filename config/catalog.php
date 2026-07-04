<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Catálogo API — TTL de caché Redis (segundos)
    |--------------------------------------------------------------------------
    | Se invalida automáticamente al crear/actualizar/eliminar productos o categorías.
    */

    'cache_ttl' => (int) env('CATALOG_CACHE_TTL', 3600),

];
