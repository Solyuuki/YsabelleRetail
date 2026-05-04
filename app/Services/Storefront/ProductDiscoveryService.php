<?php

namespace App\Services\Storefront;

use App\Models\Catalog\Product;
use App\Support\Storefront\ProductMediaResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProductDiscoveryService
{
    private const CATEGORY_KEYWORDS = [
        'running' => ['running', 'runner', 'runners', 'jogging', 'jogger', 'tempo'],
        'sneakers' => ['sneaker', 'sneakers', 'casual', 'street'],
        'basketball-shoes' => ['basketball', 'court', 'hoops', 'rebound', 'dunk'],
        'lifestyle-shoes' => ['lifestyle', 'daily', 'everyday', 'fashion', 'city'],
        'training-shoes' => ['sport', 'sports', 'gym', 'training', 'trainer', 'workout', 'active'],
        'walking-shoes' => ['walking', 'walker', 'stroll', 'comfort walk'],
        'slip-ons' => ['slip-on', 'slip ons', 'loafer', 'easy-on'],
        'boots-high-cut' => ['boot', 'boots', 'high-cut', 'rugged', 'hike'],
    ];

    private const USE_CASE_CATEGORY_MAP = [
        'daily' => ['sneakers', 'lifestyle-shoes', 'slip-ons'],
        'running' => ['running'],
        'walking' => ['walking-shoes', 'running'],
        'gym' => ['training-shoes', 'basketball-shoes'],
        'performance' => ['basketball-shoes', 'training-shoes', 'running'],
    ];

    private const COLOR_KEYWORDS = [
        'black' => ['black', 'onyx', 'shadow'],
        'white' => ['white'],
        'ivory' => ['ivory', 'cream'],
        'blue' => ['blue', 'azure', 'navy'],
        'graphite' => ['graphite', 'grey', 'gray', 'charcoal'],
        'gold' => ['gold', 'metallic'],
        'volt' => ['volt', 'neon', 'lime'],
    ];

    private const STOP_WORDS = [
        'a', 'an', 'and', 'are', 'by', 'cart', 'checkout', 'choose', 'daily', 'do', 'find', 'for', 'from', 'have',
        'help', 'i', 'image', 'in', 'is', 'it', 'like', 'me', 'my', 'need', 'of', 'on', 'or', 'please', 'recommend',
        'search', 'shoe', 'shoes', 'show', 'similar', 'something', 'that', 'the', 'to', 'under', 'use', 'want', 'what',
        'with',
    ];

    public function __construct(
        private readonly ProductMediaResolver $productMedia,
    ) {}

    public function buildCriteriaFromText(string $text): array
    {
        $normalized = Str::lower(trim($text));

        return $this->normalizeCriteria([
            'search' => $text,
            'category' => $this->detectCategory($normalized),
            'color' => $this->detectColor($normalized),
            'use_case' => $this->detectUseCase($normalized),
            'size' => $this->detectSize($normalized),
            'max_price' => $this->detectMaxPrice($normalized),
            'min_price' => $this->detectMinPrice($normalized),
            'keywords' => $this->keywordsFromText($text),
        ]);
    }

    public function normalizeCriteria(array $criteria): array
    {
        $keywords = collect([
            $criteria['keywords'] ?? [],
            $this->keywordsFromText((string) ($criteria['search'] ?? '')),
            $this->keywordsFromText((string) ($criteria['brand_style'] ?? '')),
            $this->keywordsFromText((string) ($criteria['filename'] ?? '')),
        ])->flatten()
            ->map(fn (mixed $keyword): string => Str::lower(trim((string) $keyword)))
            ->filter(fn (string $keyword): bool => $keyword !== '' && ! in_array($keyword, self::STOP_WORDS, true))
            ->unique()
            ->values()
            ->all();

        $category = $criteria['category'] ?? $this->detectCategory((string) ($criteria['search'] ?? ''));
        $color = $criteria['color'] ?? $this->detectColor((string) ($criteria['search'] ?? ''));
        $useCase = $criteria['use_case'] ?? $this->detectUseCase((string) ($criteria['search'] ?? ''));
        $size = $criteria['size'] ?? $this->detectSize((string) ($criteria['search'] ?? ''));
        $maxPrice = $criteria['max_price'] ?? $this->detectMaxPrice((string) ($criteria['search'] ?? ''));
        $minPrice = $criteria['min_price'] ?? $this->detectMinPrice((string) ($criteria['search'] ?? ''));

        return [
            'search' => trim((string) ($criteria['search'] ?? '')),
            'brand_style' => trim((string) ($criteria['brand_style'] ?? '')),
            'filename' => trim((string) ($criteria['filename'] ?? '')),
            'category' => $category ? Str::lower($category) : null,
            'color' => $color ? Str::lower($color) : null,
            'use_case' => $useCase ? Str::lower($useCase) : null,
            'size' => $size ? (string) $size : null,
            'max_price' => $maxPrice !== null ? (float) $maxPrice : null,
            'min_price' => $minPrice !== null ? (float) $minPrice : null,
            'keywords' => $keywords,
        ];
    }

    public function applyBrowseFilters(Builder $query, array $filters): Builder
    {
        $criteria = $this->normalizeCriteria($filters);

        if (($filters['status'] ?? null) !== null) {
            $query->where('status', $filters['status']);
        }

        if (($filters['featured'] ?? null) !== null) {
            $query->where('is_featured', (bool) $filters['featured']);
        }

        if ($categoryId = $filters['category_id'] ?? null) {
            $query->where('category_id', $categoryId);
        }

        if ($categorySlug = $filters['category'] ?? $criteria['category']) {
            $query->whereHas('category', function (Builder $builder) use ($categorySlug): void {
                $builder->where('slug', $categorySlug);
            });
        }

        if ($criteria['color']) {
            $query->whereHas('variants', function (Builder $builder) use ($criteria): void {
                $builder->where('option_values', 'like', '%'.$criteria['color'].'%');
            });
        }

        if ($criteria['min_price'] !== null) {
            $query->where('base_price', '>=', $criteria['min_price']);
        }

        if ($criteria['max_price'] !== null) {
            $query->where('base_price', '<=', $criteria['max_price']);
        }

        if ($criteria['size']) {
            $query->whereHas('variants', function (Builder $builder) use ($criteria): void {
                $builder->where('option_values', 'like', '%'.$criteria['size'].'%');
            });
        }

        if ($criteria['use_case'] && isset(self::USE_CASE_CATEGORY_MAP[$criteria['use_case']])) {
            $query->whereHas('category', function (Builder $builder) use ($criteria): void {
                $builder->whereIn('slug', self::USE_CASE_CATEGORY_MAP[$criteria['use_case']]);
            });
        }

        if ($criteria['search'] !== '' || $criteria['keywords'] !== []) {
            $search = $criteria['search'];
            $keywords = $criteria['keywords'];

            $query->where(function (Builder $builder) use ($search, $keywords): void {
                if ($search !== '') {
                    $builder
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('style_code', 'like', "%{$search}%")
                        ->orWhere('short_description', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                }

                foreach ($keywords as $keyword) {
                    $builder
                        ->orWhere('name', 'like', "%{$keyword}%")
                        ->orWhere('style_code', 'like', "%{$keyword}%")
                        ->orWhere('short_description', 'like', "%{$keyword}%")
                        ->orWhere('description', 'like', "%{$keyword}%")
                        ->orWhereHas('category', function (Builder $categoryQuery) use ($keyword): void {
                            $categoryQuery
                                ->where('name', 'like', "%{$keyword}%")
                                ->orWhere('slug', 'like', "%{$keyword}%");
                        })
                        ->orWhereHas('variants', function (Builder $variantQuery) use ($keyword): void {
                            $variantQuery
                                ->where('name', 'like', "%{$keyword}%")
                                ->orWhere('sku', 'like', "%{$keyword}%")
                                ->orWhere('option_values', 'like', "%{$keyword}%");
                        });
                }
            });
        }

        return $query;
    }

    public function findMatches(array $criteria, int $limit = 6): array
    {
        $normalized = $this->normalizeCriteria($criteria);

        $strictMatches = $this->rankMatches($normalized, $limit, strict: true, respectBudget: true);

        if ($strictMatches->isNotEmpty()) {
            return [
                'criteria' => $normalized,
                'products' => $strictMatches,
                'used_fallback' => false,
            ];
        }

        return [
            'criteria' => $normalized,
            'products' => $this->rankMatches($normalized, $limit, strict: false, respectBudget: false),
            'used_fallback' => true,
        ];
    }

    public function lowStockProducts(int $limit = 4): Collection
    {
        return $this->activeProducts()
            ->get()
            ->filter(fn (Product $product): bool => $this->availabilityState($product)['state'] === 'low_stock')
            ->sortBy(fn (Product $product): int => $this->lowestVariantAvailability($product))
            ->take($limit)
            ->values();
    }

    public function formatProduct(Product $product): array
    {
        $availability = $this->availabilityState($product);
        $colors = $product->variants
            ->map(fn ($variant): ?string => data_get($variant->option_values, 'color'))
            ->filter()
            ->unique()
            ->values();

        $sizes = $product->variants
            ->map(fn ($variant): ?string => data_get($variant->option_values, 'size'))
            ->filter()
            ->unique()
            ->sortBy(fn (string $size): int => (int) preg_replace('/\D+/', '', $size))
            ->values();

        return [
            'name' => $product->name,
            'category' => $product->category?->name ?? 'Collection',
            'slug' => $product->slug,
            'style_code' => $product->style_code,
            'price' => (float) $product->base_price,
            'price_label' => '₱'.number_format((float) $product->base_price, 0),
            'compare_at_price' => $product->compare_at_price ? (float) $product->compare_at_price : null,
            'compare_at_price_label' => $product->compare_at_price ? '₱'.number_format((float) $product->compare_at_price, 0) : null,
            'is_featured' => (bool) $product->is_featured,
            'is_new_arrival' => (bool) $product->storefront_new_arrival,
            'shows_new_badge' => (bool) $product->shows_new_badge,
            'shows_sale_badge' => (bool) $product->shows_sale_badge,
            'rating_average' => round((float) ($product->rating_average ?? 0), 1),
            'short_description' => $product->short_description,
            'url' => route('storefront.catalog.products.show', $product),
            'image_url' => $this->productMedia->imageUrlFor($product),
            'image_alt' => $this->productMedia->altTextFor($product),
            'colors' => $colors->all(),
            'sizes' => $sizes->all(),
            'availability' => $availability,
        ];
    }

    private function rankMatches(array $criteria, int $limit, bool $strict, bool $respectBudget): Collection
    {
        $ranked = $this->activeProducts()
            ->get()
            ->map(function (Product $product) use ($criteria, $strict, $respectBudget): ?array {
                if ($strict && ! $this->passesStrictFilters($product, $criteria, $respectBudget)) {
                    return null;
                }

                $score = $this->scoreProduct($product, $criteria, $respectBudget);

                if (! $strict && $score <= 0) {
                    return null;
                }

                return [
                    'product' => $product,
                    'score' => $score,
                    'price_distance' => $this->priceDistance($product, $criteria),
                    'available_quantity' => $this->availableQuantity($product),
                ];
            })
            ->filter()
            ->values()
            ->all();

        usort($ranked, function (array $left, array $right): int {
            return [$right['score'], $left['price_distance'], $right['available_quantity']]
                <=>
                [$left['score'], $right['price_distance'], $left['available_quantity']];
        });

        return collect($ranked)
            ->take($limit)
            ->pluck('product')
            ->values();
    }

    private function passesStrictFilters(Product $product, array $criteria, bool $respectBudget): bool
    {
        if ($criteria['category'] && $product->category?->slug !== $criteria['category']) {
            return false;
        }

        if ($criteria['use_case'] && isset(self::USE_CASE_CATEGORY_MAP[$criteria['use_case']])) {
            if (! in_array($product->category?->slug, self::USE_CASE_CATEGORY_MAP[$criteria['use_case']], true)) {
                return false;
            }
        }

        if ($criteria['color'] && ! $this->productMatchesText($product, $criteria['color'])) {
            return false;
        }

        if ($criteria['size'] && ! $this->productHasSize($product, $criteria['size'])) {
            return false;
        }

        if ($respectBudget && $criteria['max_price'] !== null && (float) $product->base_price > $criteria['max_price']) {
            return false;
        }

        if ($respectBudget && $criteria['min_price'] !== null && (float) $product->base_price < $criteria['min_price']) {
            return false;
        }

        if (($criteria['search'] !== '' || $criteria['keywords'] !== []) && ! $this->hasStructuredIntent($criteria)) {
            return collect($criteria['keywords'])
                ->contains(fn (string $keyword): bool => $this->productMatchesText($product, $keyword));
        }

        return true;
    }

    private function scoreProduct(Product $product, array $criteria, bool $respectBudget): int
    {
        $score = 0;

        if ($criteria['category'] && $product->category?->slug === $criteria['category']) {
            $score += 30;
        }

        if ($criteria['use_case'] && isset(self::USE_CASE_CATEGORY_MAP[$criteria['use_case']])) {
            if (in_array($product->category?->slug, self::USE_CASE_CATEGORY_MAP[$criteria['use_case']], true)) {
                $score += 24;
            }
        }

        if ($criteria['color'] && $this->productMatchesText($product, $criteria['color'])) {
            $score += 18;
        }

        if ($criteria['size'] && $this->productHasSize($product, $criteria['size'])) {
            $score += 14;
        }

        foreach ($criteria['keywords'] as $keyword) {
            if ($this->productMatchesName($product, $keyword)) {
                $score += 10;

                continue;
            }

            if ($this->productMatchesText($product, $keyword)) {
                $score += 5;
            }
        }

        if ($criteria['search'] !== '' && $this->productMatchesText($product, $criteria['search'])) {
            $score += 12;
        }

        if ($respectBudget && $criteria['max_price'] !== null && (float) $product->base_price <= $criteria['max_price']) {
            $score += 10;
        }

        if ($respectBudget && $criteria['min_price'] !== null && (float) $product->base_price >= $criteria['min_price']) {
            $score += 8;
        }

        $availability = $this->availabilityState($product);

        $score += match ($availability['state']) {
            'in_stock' => 8,
            'low_stock' => 5,
            default => -6,
        };

        if ($product->is_featured) {
            $score += 2;
        }

        return $score;
    }

    private function hasStructuredIntent(array $criteria): bool
    {
        return filled($criteria['category'])
            || filled($criteria['color'])
            || filled($criteria['use_case'])
            || filled($criteria['size'])
            || $criteria['max_price'] !== null
            || $criteria['min_price'] !== null;
    }

    private function activeProducts(): Builder
    {
        return Product::query()
            ->with(['category', 'variants.inventoryItem'])
            ->where('status', 'active');
    }

    private function productMatchesName(Product $product, string $text): bool
    {
        return str_contains(Str::lower($product->name), Str::lower($text));
    }

    private function productMatchesText(Product $product, string $text): bool
    {
        return str_contains($this->searchableText($product), Str::lower($text));
    }

    private function productHasSize(Product $product, string $size): bool
    {
        return $product->variants->contains(function ($variant) use ($size): bool {
            return (string) data_get($variant->option_values, 'size') === (string) $size;
        });
    }

    private function searchableText(Product $product): string
    {
        return Str::lower(collect([
            $product->name,
            $product->style_code,
            $product->short_description,
            $product->description,
            $product->category?->name,
            $product->category?->slug,
            $product->variants->pluck('name')->implode(' '),
            $product->variants->pluck('sku')->implode(' '),
            $product->variants
                ->map(fn ($variant): string => implode(' ', array_filter([
                    data_get($variant->option_values, 'color'),
                    data_get($variant->option_values, 'size'),
                ])))
                ->implode(' '),
        ])->filter()->implode(' '));
    }

    private function availabilityState(Product $product): array
    {
        $available = $this->availableQuantity($product);
        $variantSnapshots = $product->variants
            ->map(function ($variant): array {
                $availableQuantity = (int) ($variant->inventoryItem?->available_quantity ?? 0);
                $reorderLevel = max((int) ($variant->inventoryItem?->reorder_level ?? 0), 3);

                return [
                    'available' => $availableQuantity,
                    'reorder_level' => $reorderLevel,
                ];
            });

        if ($available <= 0) {
            return [
                'state' => 'sold_out',
                'label' => 'Sold out',
                'quantity' => 0,
            ];
        }

        $limitedSizes = $variantSnapshots->contains(function (array $snapshot): bool {
            return $snapshot['available'] <= $snapshot['reorder_level'];
        });

        if ($limitedSizes) {
            return [
                'state' => 'low_stock',
                'label' => 'Low stock on select sizes',
                'quantity' => $available,
            ];
        }

        return [
            'state' => 'in_stock',
            'label' => 'In stock',
            'quantity' => $available,
        ];
    }

    private function availableQuantity(Product $product): int
    {
        return (int) $product->variants->sum(function ($variant): int {
            return (int) ($variant->inventoryItem?->available_quantity ?? 0);
        });
    }

    private function lowestVariantAvailability(Product $product): int
    {
        return (int) $product->variants
            ->map(fn ($variant): int => (int) ($variant->inventoryItem?->available_quantity ?? PHP_INT_MAX))
            ->min();
    }

    private function priceDistance(Product $product, array $criteria): float
    {
        $price = (float) $product->base_price;

        if ($criteria['max_price'] !== null && $price > $criteria['max_price']) {
            return $price - $criteria['max_price'];
        }

        if ($criteria['min_price'] !== null && $price < $criteria['min_price']) {
            return $criteria['min_price'] - $price;
        }

        return 0.0;
    }

    private function detectCategory(string $text): ?string
    {
        $normalized = Str::lower($text);

        foreach (self::CATEGORY_KEYWORDS as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($normalized, $keyword)) {
                    return $category;
                }
            }
        }

        return null;
    }

    private function detectColor(string $text): ?string
    {
        $normalized = Str::lower($text);

        foreach (self::COLOR_KEYWORDS as $color => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($normalized, $keyword)) {
                    return $color;
                }
            }
        }

        return null;
    }

    private function detectUseCase(string $text): ?string
    {
        $normalized = Str::lower($text);

        return match (true) {
            str_contains($normalized, 'daily') || str_contains($normalized, 'everyday') || str_contains($normalized, 'casual') => 'daily',
            str_contains($normalized, 'running') || str_contains($normalized, 'runner') || str_contains($normalized, 'jog') => 'running',
            str_contains($normalized, 'walking') || str_contains($normalized, 'walk') => 'walking',
            str_contains($normalized, 'gym') || str_contains($normalized, 'training') || str_contains($normalized, 'workout') => 'gym',
            str_contains($normalized, 'performance') || str_contains($normalized, 'premium support') => 'performance',
            default => null,
        };
    }

    private function detectSize(string $text): ?string
    {
        if (! preg_match('/(?:size\s*)?(6|7|8|9|10|11|12)\b/i', $text, $matches)) {
            return null;
        }

        return $matches[1];
    }

    private function detectMaxPrice(string $text): ?float
    {
        if (! preg_match('/(?:under|below|less than|max(?:imum)?|up to|within)\s*(?:php|₱)?\s*([\d,]+)/i', $text, $matches)) {
            return null;
        }

        return (float) str_replace(',', '', $matches[1]);
    }

    private function detectMinPrice(string $text): ?float
    {
        if (! preg_match('/(?:over|above|more than|min(?:imum)?|at least)\s*(?:php|₱)?\s*([\d,]+)/i', $text, $matches)) {
            return null;
        }

        return (float) str_replace(',', '', $matches[1]);
    }

    private function keywordsFromText(string $text): array
    {
        return collect(preg_split('/[^a-z0-9]+/i', Str::lower($text)) ?: [])
            ->filter(fn (string $token): bool => $token !== '' && strlen($token) > 1 && ! in_array($token, self::STOP_WORDS, true))
            ->unique()
            ->values()
            ->all();
    }
}
