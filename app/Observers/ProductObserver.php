<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\CatalogCacheService;

class ProductObserver
{
    public function created(Product $product): void
    {
        $this->flushCatalog();
    }

    public function updated(Product $product): void
    {
        $this->flushCatalog();
    }

    public function deleted(Product $product): void
    {
        $this->flushCatalog();
    }

    public function restored(Product $product): void
    {
        $this->flushCatalog();
    }

    private function flushCatalog(): void
    {
        app(CatalogCacheService::class)->flush();
    }
}
