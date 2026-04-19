<?php

namespace App\Services\Catalog;

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use Illuminate\Database\Eloquent\Builder;
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

        return $this->featuredProductsQuery()
            ->limit($limit)
            ->get();
    }

    public function showcaseProducts(?Product $heroProduct = null, int $limit = 4): Collection
    {
        if (! $this->catalogIsAvailable()) {
            return collect();
        }

        $heroId = $heroProduct?->getKey();

        $featured = (clone $this->featuredProductsQuery())
            ->when($heroId, fn (Builder $query) => $query->where('id', '!=', $heroId))
            ->limit($limit)
            ->get();

        if ($featured->count() >= $limit) {
            return $featured->values();
        }

        $excludedIds = $featured->pluck('id');

        if ($heroId) {
            $excludedIds->push($heroId);
        }

        $fallback = Product::query()
            ->with(['category', 'variants.inventoryItem'])
            ->where('status', 'active')
            ->whereNotIn('id', $excludedIds->unique()->values()->all())
            ->orderByDesc('is_featured')
            ->orderByRaw('CASE WHEN featured_rank IS NULL THEN 1 ELSE 0 END')
            ->orderBy('featured_rank')
            ->latest()
            ->limit($limit - $featured->count())
            ->get();

        return $featured->concat($fallback)->values();
    }

    public function heroProduct(): ?Product
    {
        if (! $this->catalogIsAvailable()) {
            return null;
        }

        return (clone $this->featuredProductsQuery())
            ->whereNotNull('primary_image_url')
            ->first()
            ?? $this->featuredProductsQuery()->first();
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

    public function navigationCategories(): Collection
    {
        if (! $this->catalogIsAvailable()) {
            return collect();
        }

        return Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
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

        if ($categorySlug = $filters['category'] ?? null) {
            $query->whereHas('category', function ($builder) use ($categorySlug): void {
                $builder->where('slug', $categorySlug);
            });
        }

        if ($featured = $filters['featured'] ?? null) {
            $query->where('is_featured', (bool) $featured);
        }

        match ($filters['sort'] ?? 'featured') {
            'price_asc' => $query->orderBy('base_price'),
            'price_desc' => $query->orderByDesc('base_price'),
            'newest' => $query->latest(),
            'name' => $query->orderBy('name'),
            default => $query->orderByDesc('is_featured')->orderBy('name'),
        };

        return $query
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

    private function featuredProductsQuery(): Builder
    {
        return Product::query()
            ->with(['category', 'variants.inventoryItem'])
            ->where('status', 'active')
            ->where('is_featured', true)
            ->orderByRaw('CASE WHEN featured_rank IS NULL THEN 1 ELSE 0 END')
            ->orderBy('featured_rank')
            ->latest();
    }
}
