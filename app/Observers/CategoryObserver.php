<?php

namespace App\Observers;

use App\Models\Category;
use App\Services\CatalogCacheService;

class CategoryObserver
{
    public function created(Category $category): void
    {
        $this->flushCatalog();
    }

    public function updated(Category $category): void
    {
        $this->flushCatalog();
    }

    public function deleted(Category $category): void
    {
        $this->flushCatalog();
    }

    private function flushCatalog(): void
    {
        app(CatalogCacheService::class)->flush();
    }
}
