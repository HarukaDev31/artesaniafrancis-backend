<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ProductCatalogQuery
{
    public const SORT_OPTIONS = [
        'recommended',
        'price_asc',
        'price_desc',
        'name_asc',
    ];

    /**
     * @return array{0: Builder, 1: int}
     */
    public function fromRequest(Request $request): array
    {
        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'search' => 'sometimes|string|max:100',
            'category' => 'sometimes|string|alpha_dash|max:100',
            'categories' => 'sometimes|string|max:500',
            'sort' => 'sometimes|string|in:'.implode(',', self::SORT_OPTIONS),
            'featured' => 'sometimes|boolean',
        ]);

        $query = Product::query()
            ->active()
            ->with('category');

        if (! empty($validated['search'])) {
            $term = '%'.$validated['search'].'%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        if (! empty($validated['category'])) {
            $query->whereHas('category', fn (Builder $q) => $q->where('slug', $validated['category']));
        } elseif (! empty($validated['categories'])) {
            $slugs = array_values(array_filter(array_map(
                static fn (string $slug): string => trim($slug),
                explode(',', $validated['categories'])
            )));

            if ($slugs !== []) {
                $query->whereHas('category', fn (Builder $q) => $q->whereIn('slug', $slugs));
            }
        }

        if (! empty($validated['featured'])) {
            $query->featured();
        }

        $this->applySort($query, $validated['sort'] ?? 'recommended');

        $perPage = $validated['per_page'] ?? 9;

        return [$query, $perPage];
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'price_asc' => $query->orderBy('price')->orderBy('name'),
            'price_desc' => $query->orderByDesc('price')->orderBy('name'),
            'name_asc' => $query->orderBy('name'),
            default => $query
                ->orderByDesc('is_featured')
                ->orderBy('sort_order')
                ->orderByDesc('id'),
        };
    }
}
