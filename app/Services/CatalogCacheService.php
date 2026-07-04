<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CatalogCacheService
{
    private const VERSION_KEY = 'catalog:version';

    public function ttl(): int
    {
        return (int) config('catalog.cache_ttl', 3600);
    }

    public function productsListKey(Request $request): string
    {
        $params = $request->query();
        ksort($params);

        return 'products:'.md5(http_build_query($params));
    }

    public function productShowKey(string $slug): string
    {
        return 'product:'.$slug;
    }

    public function categoriesKey(): string
    {
        return 'categories';
    }

    public function remember(string $key, callable $callback): mixed
    {
        return Cache::remember($this->versionedKey($key), $this->ttl(), $callback);
    }

    /**
     * Invalida todo el catálogo API (productos + categorías) en un solo paso.
     */
    public function flush(): void
    {
        Cache::forever(self::VERSION_KEY, $this->version() + 1);
    }

    private function versionedKey(string $key): string
    {
        return 'catalog:v'.$this->version().':'.$key;
    }

    private function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }
}
