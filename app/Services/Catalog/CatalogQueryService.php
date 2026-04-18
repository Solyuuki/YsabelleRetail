<?php

namespace App\Services\Catalog;

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CatalogQueryService
{
    public function featuredProducts(int $limit = 6): Collection
    {
        if (! $this->catalogIsAvailable()) {
            return collect();
        }

        return Product::query()
            ->with(['category', 'variants.inventoryItem'])
            ->where('status', 'active')
            ->where('is_featured', true)
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function categories(int $perPage = 12): LengthAwarePaginator
    {
        if (! $this->catalogIsAvailable()) {
            return $this->emptyPaginator($perPage);
        }

        return Category::query()
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function products(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        if (! $this->catalogIsAvailable()) {
            return $this->emptyPaginator($perPage);
        }

        $query = Product::query()
            ->with(['category', 'variants.inventoryItem'])
            ->withCount('variants');

        if (($filters['status'] ?? null) !== null) {
            $query->where('status', $filters['status']);
        }

        if ($search = $filters['search'] ?? null) {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('style_code', 'like', "%{$search}%");
            });
        }

        if ($categoryId = $filters['category_id'] ?? null) {
            $query->where('category_id', $categoryId);
        }

        if ($featured = $filters['featured'] ?? null) {
            $query->where('is_featured', (bool) $featured);
        }

        return $query
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function catalogIsAvailable(): bool
    {
        try {
            return Schema::hasTable('categories') && Schema::hasTable('products');
        } catch (Throwable) {
            return false;
        }
    }

    private function emptyPaginator(int $perPage): LengthAwarePaginator
    {
        return new Paginator([], 0, $perPage);
    }
}
