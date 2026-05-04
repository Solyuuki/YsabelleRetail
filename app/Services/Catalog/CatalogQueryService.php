<?php

namespace App\Services\Catalog;

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Support\Storefront\CatalogCollection;
use App\Services\Storefront\ProductDiscoveryService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CatalogQueryService
{
    public function __construct(
        private readonly ProductDiscoveryService $productDiscovery,
    ) {}

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

        $showcase = $featured->concat($fallback)->values();

        if ($heroProduct && ! $showcase->contains(fn (Product $product) => $product->is($heroProduct))) {
            $showcase->push($heroProduct);
        }

        return $showcase
            ->unique(fn (Product $product) => $product->getKey())
            ->take($limit)
            ->values();
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

        $filters = $this->resolveBrowseFilters($filters);
        $query = Product::query()
            ->with(['category', 'variants.inventoryItem'])
            ->withCount('variants')
            ->where('status', 'active');

        $this->productDiscovery->applyBrowseFilters($query, $filters);

        $this->applyBrowseSort($query, $filters['sort'] ?? 'featured');

        $products = $query
            ->paginate($perPage)
            ->withQueryString();

        $this->decorateBrowseProducts($products->getCollection(), $filters);

        return $products;
    }

    public function resolveBrowseFilters(array $filters): array
    {
        $collection = $filters['collection'] ?? null;

        if (! filled($filters['sort'] ?? null)) {
            $filters['sort'] = CatalogCollection::defaultSort($collection) ?? 'featured';
        }

        return $filters;
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

    private function applyBrowseSort(Builder $query, string $sort): void
    {
        match ($sort) {
            'price_asc' => $query->orderBy('base_price')->orderBy('name'),
            'price_desc' => $query->orderByDesc('base_price')->orderBy('name'),
            'newest' => $this->applyNewArrivalSort($query),
            'name' => $query->orderBy('name'),
            'best_sellers' => $this->applyBestSellerSort($query),
            default => $query->orderByDesc('is_featured')->orderBy('name'),
        };
    }

    private function applyNewArrivalSort(Builder $query): void
    {
        $query
            ->orderByDesc('created_at')
            ->orderByDesc('is_featured')
            ->orderByRaw('CASE WHEN featured_rank IS NULL THEN 1 ELSE 0 END')
            ->orderBy('featured_rank')
            ->orderByDesc('review_count')
            ->orderByDesc('rating_average')
            ->orderBy('name');
    }

    private function applyBestSellerSort(Builder $query): void
    {
        $query
            ->withCount([
                'orderItems as completed_order_lines' => function (Builder $builder): void {
                    $builder->whereHas('order', function (Builder $orderQuery): void {
                        $orderQuery
                            ->where('status', 'completed')
                            ->where('payment_status', 'paid');
                    });
                },
            ])
            ->withSum([
                'orderItems as units_sold' => function (Builder $builder): void {
                    $builder->whereHas('order', function (Builder $orderQuery): void {
                        $orderQuery
                            ->where('status', 'completed')
                            ->where('payment_status', 'paid');
                    });
                },
            ], 'quantity')
            ->orderByRaw('COALESCE(units_sold, 0) DESC')
            ->orderByRaw('COALESCE(completed_order_lines, 0) DESC')
            ->orderByRaw($this->bestSellerFallbackScoreExpression().' DESC')
            ->orderBy('name');
    }

    private function bestSellerFallbackScoreExpression(): string
    {
        return implode(' + ', [
            'CASE WHEN compare_at_price IS NOT NULL AND compare_at_price > base_price THEN 100000 ELSE 0 END',
            'COALESCE(review_count, 0) * 100',
            'COALESCE(rating_average, 0) * 1000',
            'CASE WHEN is_featured = 1 THEN 5000 ELSE 0 END',
            'CASE WHEN featured_rank IS NULL THEN 0 ELSE (1000 - featured_rank) END',
        ]);
    }

    private function decorateBrowseProducts(Collection $products, array $filters): void
    {
        $isNewArrivals = CatalogCollection::isNewArrivals($filters['collection'] ?? null);

        $products->each(function (Product $product) use ($isNewArrivals): void {
            $product->markAsStorefrontNewArrival($isNewArrivals);
        });
    }
}
