<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\CatalogCacheService;

class CategoryController extends Controller
{
    /**
     * GET /api/categories
     */
    public function index(CatalogCacheService $cache)
    {
        $payload = $cache->remember($cache->categoriesKey(), function () {
            $categories = Category::active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            return CategoryResource::collection($categories)
                ->response()
                ->getData(true);
        });

        return response()->json($payload);
    }
}
