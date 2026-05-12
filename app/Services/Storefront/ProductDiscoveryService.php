<?php

namespace App\Services\Storefront;

use App\Models\Catalog\Product;
use App\Services\Storefront\Assistant\StorefrontCommerceQueryParser;
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
        'hiking' => ['boots-high-cut', 'walking-shoes'],
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
        'help', 'i', 'image', 'in', 'is', 'it', 'kayo', 'like', 'may', 'me', 'my', 'na', 'need', 'ng', 'nito', 'of',
        'on', 'or', 'please', 'recommend', 'search', 'shoe', 'shoes', 'show', 'similar', 'something', 'that', 'the',
        'to', 'under', 'use', 'want', 'what', 'with', 'yung',
    ];

    public function __construct(
        private readonly ProductMediaResolver $productMedia,
        private readonly StorefrontCommerceQueryParser $commerceQueryParser,
    ) {}

    public function buildCriteriaFromText(string $text, array $commerce = []): array
    {
        $normalized = Str::lower(trim($text));
        $commerce = $commerce === [] ? $this->commerceQueryParser->parse($text) : $commerce;

        return $this->normalizeCriteria([
            'search' => $commerce['query'] ?? $text,
            'product_name' => $commerce['entities']['product_name'] ?? null,
            'category' => $commerce['entities']['category'] ?? $this->detectCategory($normalized),
            'color' => $commerce['entities']['color'] ?? $this->detectColor($normalized),
            'use_case' => $commerce['entities']['use_case'] ?? $this->detectUseCase($normalized),
            'size' => $commerce['entities']['size'] ?? $this->detectSize($normalized),
            'max_price' => $commerce['entities']['budget_max'] ?? $this->commerceMaxPrice($normalized),
            'min_price' => $commerce['entities']['budget_min'] ?? $this->commerceMinPrice($normalized),
            'gender' => $commerce['entities']['gender'] ?? null,
            'affordable' => (bool) ($commerce['flags']['affordable'] ?? false),
            'keywords' => $commerce['keywords'] ?? $this->keywordsFromText($text),
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
        $maxPrice = $criteria['max_price'] ?? $this->commerceMaxPrice((string) ($criteria['search'] ?? ''));
        $minPrice = $criteria['min_price'] ?? $this->commerceMinPrice((string) ($criteria['search'] ?? ''));
        $affordable = (bool) ($criteria['affordable'] ?? false);

        return [
            'search' => trim((string) ($criteria['search'] ?? '')),
            'brand_style' => trim((string) ($criteria['brand_style'] ?? '')),
            'filename' => trim((string) ($criteria['filename'] ?? '')),
            'product_name' => trim((string) ($criteria['product_name'] ?? '')) ?: null,
            'category' => $category ? Str::lower($category) : null,
            'color' => $color ? Str::lower($color) : null,
            'use_case' => $useCase ? Str::lower($useCase) : null,
            'size' => $size ? (string) $size : null,
            'max_price' => $maxPrice !== null ? (float) $maxPrice : null,
            'min_price' => $minPrice !== null ? (float) $minPrice : null,
            'gender' => trim((string) ($criteria['gender'] ?? '')) ?: null,
            'affordable' => $affordable,
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

    public function findDirectProductMatch(string $message, array $pageContext = [], array $commerce = []): array
    {
        $commerce = $commerce === [] ? $this->commerceQueryParser->parse($message, $pageContext) : $commerce;

        if (($commerce['flags']['references_current_product'] ?? false) || $this->referencesCurrentProduct($message)) {
            $currentProduct = $this->currentProductFromContext($pageContext);

            if ($currentProduct) {
                return [
                    'status' => 'current_product',
                    'product' => $currentProduct,
                    'query' => $currentProduct->name,
                    'match_type' => 'current_product',
                ];
            }
        }

        $query = $this->directLookupQuery($message, $commerce);

        if ($query === '') {
            return [
                'status' => 'none',
                'product' => null,
                'query' => null,
                'match_type' => null,
            ];
        }

        $activeMatch = $this->bestNamedProductMatchMeta($query, activeOnly: true);

        if ($activeMatch) {
            return [
                'status' => $activeMatch['match_type'] === 'exact' ? 'active_match' : 'active_close_match',
                'product' => $activeMatch['product'],
                'query' => $activeMatch['product']->name,
                'match_type' => $activeMatch['match_type'],
                'matched_query' => $query,
            ];
        }

        $inactiveMatch = $this->bestNamedProductMatchMeta($query, activeOnly: false);

        if ($inactiveMatch && $inactiveMatch['product']->status !== 'active') {
            return [
                'status' => 'inactive_match',
                'product' => $inactiveMatch['product'],
                'query' => $inactiveMatch['product']->name,
                'match_type' => $inactiveMatch['match_type'],
                'matched_query' => $query,
            ];
        }

        return [
            'status' => 'none',
            'product' => null,
            'query' => $query,
            'match_type' => null,
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
            'shows_new_badge' => (bool) $product->shows_new_badge,
            'shows_sale_badge' => (bool) $product->shows_sale_badge,
            'rating_average' => $product->shows_rating_summary ? round((float) ($product->rating_average ?? 0), 1) : null,
            'review_count' => (int) $product->review_count,
            'shows_rating_summary' => (bool) $product->shows_rating_summary,
            'rating_display_state' => (string) $product->rating_display_state,
            'short_description' => $product->short_description,
            'url' => route('storefront.catalog.products.show', $product),
            'image_url' => $this->productMedia->imageUrlFor($product),
            'image_alt' => $this->productMedia->altTextFor($product),
            'colors' => $colors->all(),
            'sizes' => $sizes->all(),
            'availability' => $availability,
        ];
    }

    public function sizeAvailabilityForProduct(Product $product, string $size): array
    {
        $normalizedSize = $this->normalizeSizeValue($size);
        $matchingVariants = $product->variants->filter(function ($variant) use ($normalizedSize): bool {
            return $this->normalizeSizeValue((string) data_get($variant->option_values, 'size')) === $normalizedSize;
        })->values();

        $availableQuantity = (int) $matchingVariants->sum(function ($variant): int {
            return (int) ($variant->inventoryItem?->available_quantity ?? 0);
        });

        return [
            'requested_size' => $normalizedSize,
            'has_size' => $matchingVariants->isNotEmpty(),
            'is_available' => $availableQuantity > 0,
            'available_quantity' => $availableQuantity,
            'available_sizes' => $this->availableSizesForProduct($product),
        ];
    }

    public function availableSizesForProduct(Product $product): array
    {
        return $product->variants
            ->filter(fn ($variant): bool => (int) ($variant->inventoryItem?->available_quantity ?? 0) > 0)
            ->map(fn ($variant): ?string => $this->normalizeSizeValue((string) data_get($variant->option_values, 'size')))
            ->filter()
            ->unique()
            ->sortBy(fn (string $size): float => (float) $size)
            ->values()
            ->all();
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

        if ($criteria['color'] && ! $this->productMatchesColor($product, $criteria['color'])) {
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

        if ($criteria['color'] && $this->productMatchesColor($product, $criteria['color'])) {
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

        if (($criteria['affordable'] ?? false) === true) {
            $score += max(0, 18 - (int) floor(((float) $product->base_price) / 500));
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
            || filled($criteria['product_name'])
            || (($criteria['affordable'] ?? false) === true)
            || $criteria['max_price'] !== null
            || $criteria['min_price'] !== null;
    }

    private function activeProducts(): Builder
    {
        return Product::query()
            ->with(['category', 'variants.inventoryItem'])
            ->active();
    }

    private function catalogProducts(): Builder
    {
        return Product::query()
            ->with(['category', 'variants.inventoryItem']);
    }

    private function productMatchesName(Product $product, string $text): bool
    {
        return str_contains(Str::lower($product->name), Str::lower($text));
    }

    private function productMatchesText(Product $product, string $text): bool
    {
        return str_contains($this->searchableText($product), Str::lower($text));
    }

    private function productMatchesColor(Product $product, string $color): bool
    {
        $color = Str::lower($color);
        $variantColors = $product->variants
            ->map(fn ($variant): string => Str::lower((string) data_get($variant->option_values, 'color', '')))
            ->filter()
            ->values();

        if ($variantColors->isNotEmpty()) {
            return $variantColors->contains(function (string $variantColor) use ($color): bool {
                return str_contains($variantColor, $color);
            });
        }

        return $this->productMatchesText($product, $color);
    }

    private function productHasSize(Product $product, string $size): bool
    {
        return $product->variants->contains(function ($variant) use ($size): bool {
            return $this->normalizeSizeValue((string) data_get($variant->option_values, 'size')) === $this->normalizeSizeValue((string) $size);
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

    private function directLookupQuery(string $message, array $commerce = []): string
    {
        if (filled($commerce['entities']['product_name'] ?? null)) {
            return $this->normalizeComparableText((string) $commerce['entities']['product_name']);
        }

        if (
            filled($commerce['entities']['category'] ?? null)
            || filled($commerce['entities']['color'] ?? null)
            || filled($commerce['entities']['use_case'] ?? null)
            || filled($commerce['entities']['size'] ?? null)
            || filled($commerce['entities']['budget_min'] ?? null)
            || filled($commerce['entities']['budget_max'] ?? null)
            || (($commerce['flags']['affordable'] ?? false) === true)
        ) {
            return '';
        }

        if (($commerce['flags']['references_current_product'] ?? false) === true) {
            return '';
        }

        $normalized = $this->normalizeComparableText($message);

        if ($normalized === '' || $this->referencesCurrentProduct($normalized)) {
            return '';
        }

        foreach ([
            'can you find me ',
            'could you find me ',
            'can you show me ',
            'could you show me ',
            'do you have ',
            'can i get ',
            'please find ',
            'please show ',
            'looking for ',
            'look for ',
            'find me ',
            'show me ',
            'search for ',
            'meron ba kayo ',
            'meron ba ',
            'meron bang ',
            'hanap moko ',
            'hanap mo ko ',
            'hanap ako ',
            'find ',
            'show ',
            'have ',
            'get ',
        ] as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                $normalized = trim(substr($normalized, strlen($prefix)));
                break;
            }
        }

        return trim($normalized);
    }

    private function referencesCurrentProduct(string $message): bool
    {
        $normalized = $this->normalizeComparableText($message);

        foreach ([
            'this product',
            'this shoe',
            'this pair',
            'this item',
            'find this',
            'show this',
            'the product im viewing',
            'the product i m viewing',
            'the product on this page',
            'ito',
            'nito',
            'neto',
            'yung item na to',
            'itong item',
            'itong product',
            'itong shoe',
        ] as $phrase) {
            if (str_contains($normalized, $phrase)) {
                return true;
            }
        }

        return false;
    }

    public function currentProductFromContext(array $pageContext): ?Product
    {
        $slug = trim((string) data_get($pageContext, 'current_product.slug', ''));

        if ($slug === '') {
            return null;
        }

        return $this->activeProducts()
            ->where('slug', $slug)
            ->first();
    }

    private function bestNamedProductMatchMeta(string $query, bool $activeOnly): ?array
    {
        $normalizedQuery = $this->normalizeComparableText($query);

        if ($normalizedQuery === '' || mb_strlen($normalizedQuery) < 3) {
            return null;
        }

        $products = ($activeOnly ? $this->activeProducts() : $this->catalogProducts())
            ->get()
            ->map(function (Product $product) use ($normalizedQuery): ?array {
                $match = $this->namedMatchScore($product, $normalizedQuery);

                if (($match['score'] ?? 0) <= 0) {
                    return null;
                }

                return [
                    'product' => $product,
                    'score' => $match['score'],
                    'match_type' => $match['match_type'],
                    'available_quantity' => $this->availableQuantity($product),
                ];
            })
            ->filter()
            ->sort(function (array $left, array $right): int {
                return [$right['score'], $right['available_quantity']]
                    <=>
                    [$left['score'], $left['available_quantity']];
            })
            ->values();

        $best = $products->first();
        $second = $products->skip(1)->first();

        if (! is_array($best)) {
            return null;
        }

        if (($best['score'] ?? 0) < 780) {
            return null;
        }

        if (is_array($second) && ($best['score'] - $second['score']) < 18 && ($best['match_type'] ?? '') !== 'exact') {
            return null;
        }

        return $best;
    }

    private function namedMatchScore(Product $product, string $query): array
    {
        $name = $this->normalizeComparableText($product->name);
        $slug = $this->normalizeComparableText(str_replace('-', ' ', $product->slug));
        $styleCode = $this->normalizeComparableText($product->style_code);

        if ($query === $name) {
            return ['score' => 1200, 'match_type' => 'exact'];
        }

        if ($query === $slug) {
            return ['score' => 1180, 'match_type' => 'exact'];
        }

        if ($query === $styleCode) {
            return ['score' => 1160, 'match_type' => 'exact'];
        }

        if (str_contains($name, $query)) {
            return ['score' => 1040 - abs(strlen($name) - strlen($query)), 'match_type' => 'partial'];
        }

        if (str_contains($slug, $query) || str_contains($styleCode, $query)) {
            return ['score' => 1010, 'match_type' => 'partial'];
        }

        $queryTokens = array_values(array_filter(explode(' ', $query)));

        if (count($queryTokens) >= 2 && $this->containsOrderedTokens($name, $queryTokens)) {
            return ['score' => 960, 'match_type' => 'partial'];
        }

        if (count($queryTokens) >= 2 && $this->containsAllTokens($name, $queryTokens)) {
            return ['score' => 930, 'match_type' => 'partial'];
        }

        $distance = levenshtein($query, $name);
        $maxLength = max(strlen($query), strlen($name));

        if ($maxLength >= 5) {
            $similarity = 1 - ($distance / $maxLength);

            if ($distance <= 3 && $similarity >= 0.76) {
                return [
                    'score' => 900 - ($distance * 25),
                    'match_type' => 'fuzzy',
                ];
            }
        }

        if ($this->tokensFuzzyMatch($name, $queryTokens)) {
            return [
                'score' => 860,
                'match_type' => 'fuzzy',
            ];
        }

        return ['score' => 0, 'match_type' => null];
    }

    private function containsOrderedTokens(string $haystack, array $tokens): bool
    {
        $pattern = '/'.implode('.*', array_map(fn (string $token): string => preg_quote($token, '/'), $tokens)).'/';

        return preg_match($pattern, $haystack) === 1;
    }

    private function containsAllTokens(string $haystack, array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (! str_contains($haystack, $token)) {
                return false;
            }
        }

        return true;
    }

    private function normalizeComparableText(?string $value): string
    {
        $value = Str::lower((string) $value);
        $value = preg_replace('/[^a-z0-9]+/i', ' ', $value);

        return trim((string) preg_replace('/\s+/', ' ', $value));
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
            str_contains($normalized, 'hiking') || str_contains($normalized, 'trail') || str_contains($normalized, 'boot') => 'hiking',
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
        if (! preg_match('/(?:size|sz|us)?\s*(6|7|8|9|10|11|12)(?:\.5)?\b/i', $text, $matches)) {
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

    private function tokensFuzzyMatch(string $name, array $queryTokens): bool
    {
        if ($queryTokens === []) {
            return false;
        }

        $nameTokens = array_values(array_filter(explode(' ', $name)));

        foreach ($queryTokens as $queryToken) {
            $matched = false;

            foreach ($nameTokens as $nameToken) {
                if ($queryToken === $nameToken) {
                    $matched = true;
                    break;
                }

                if (strlen($queryToken) >= 3 && levenshtein($queryToken, $nameToken) <= 1) {
                    $matched = true;
                    break;
                }
            }

            if (! $matched) {
                return false;
            }
        }

        return true;
    }

    private function normalizeSizeValue(string $size): string
    {
        $size = trim($size);

        if (! str_contains($size, '.')) {
            return $size;
        }

        return rtrim(rtrim($size, '0'), '.');
    }

    private function commerceMaxPrice(string $text): ?float
    {
        if (! preg_match('/(?:under|below|less than|max(?:imum)?|up to|within)\s*(?:php|p|â‚±)?\s*([\d,.]+(?:\s*k)?)/i', $text, $matches)) {
            return null;
        }

        return $this->moneyValue($matches[1]);
    }

    private function commerceMinPrice(string $text): ?float
    {
        if (! preg_match('/(?:over|above|more than|min(?:imum)?|at least)\s*(?:php|p|â‚±)?\s*([\d,.]+(?:\s*k)?)/i', $text, $matches)) {
            return null;
        }

        return $this->moneyValue($matches[1]);
    }

    private function moneyValue(string $value): ?float
    {
        $normalized = Str::lower(trim($value));
        $isThousands = str_ends_with($normalized, 'k');
        $normalized = str_replace([',', ' '], '', $normalized);
        $normalized = rtrim($normalized, 'k');

        if (! is_numeric($normalized)) {
            return null;
        }

        $amount = (float) $normalized;

        return $isThousands ? $amount * 1000 : $amount;
    }
}
