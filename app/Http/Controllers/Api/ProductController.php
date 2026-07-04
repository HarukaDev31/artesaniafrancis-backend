<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Services\CatalogCacheService;
use App\Services\ProductCatalogQuery;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Listado paginado de productos activos.
     *
     * GET /api/products?page=1&per_page=9&search=rosario&category=rosarios
     *     &categories=rosarios,crucifijos&sort=price_asc&featured=1
     */
    public function index(Request $request, ProductCatalogQuery $catalogQuery, CatalogCacheService $cache)
    {
        $payload = $cache->remember($cache->productsListKey($request), function () use ($request, $catalogQuery) {
            [$query, $perPage] = $catalogQuery->fromRequest($request);

            return ProductResource::collection($query->paginate($perPage))
                ->response()
                ->getData(true);
        });

        return response()->json($payload);
    }

    /**
     * Detalle de un producto por slug.
     * GET /api/products/{slug}
     */
    public function show(string $slug, CatalogCacheService $cache)
    {
        $payload = $cache->remember($cache->productShowKey($slug), function () use ($slug) {
            $product = Product::active()
                ->with('category')
                ->where('slug', $slug)
                ->firstOrFail();

            return (new ProductResource($product))
                ->response()
                ->getData(true);
        });

        return response()->json($payload);
    }
}
