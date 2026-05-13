<?php

namespace App\Services\Admin;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Models\Storefront\VisualSearchIndexEntry;
use App\Services\Catalog\ProductAvailabilityService;
use App\Support\Storefront\ProductMediaPath;
use App\Support\Storefront\ProductMediaResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ProductVisibilityDiagnosticsService
{
    public function __construct(
        private readonly ProductAvailabilityService $availability,
        private readonly ProductMediaResolver $mediaResolver,
        private readonly ProductMediaPath $mediaPath,
    ) {}

    public function inspect(Product $product): array
    {
        $product->loadMissing(['category', 'variants.inventoryItem']);

        $availability = $this->availability->forProduct($product);
        $activeVariants = $product->variants
            ->filter(fn (ProductVariant $variant): bool => $variant->status === 'active')
            ->values();
        $pricedVariants = $activeVariants
            ->filter(fn (ProductVariant $variant): bool => $variant->price !== null)
            ->values();
        $resolvedImageUrl = $this->mediaResolver->imageUrlFor($product);
        $primaryImageValue = is_string($product->primary_image_url)
            ? trim($product->primary_image_url)
            : '';
        $localRelativeImage = $this->mediaPath->toRelativePath($primaryImageValue);
        $localImagePath = $this->mediaPath->toLocalPublicPath($primaryImageValue);
        $externalImage = $primaryImageValue !== ''
            && filter_var($primaryImageValue, FILTER_VALIDATE_URL)
            && $localImagePath === null
            && ! $this->isLocalHostUrl($primaryImageValue);
        $visualEntries = $this->visualEntriesFor($product);
        $staleVisualEntries = $visualEntries
            ->filter(fn (VisualSearchIndexEntry $entry): bool => $entry->source_updated_at?->gt($entry->indexed_at) ?? false)
            ->count();
        $hasPrice = $pricedVariants->isNotEmpty();
        $hasSearchableMetadata = filled($product->name) && filled($product->category?->name);
        $storefrontVisible = (bool) ($availability['is_discoverable'] ?? false);
        $chatbotDiscoverable = $storefrontVisible && $hasSearchableMetadata;

        $checks = [
            $this->check(
                key: 'status',
                label: 'Product status',
                state: $product->status === 'active' ? 'pass' : 'fail',
                reason: $product->status === 'active'
                    ? 'This product is active and can participate in storefront discovery.'
                    : 'This product is currently set to '.ucfirst((string) $product->status).'.',
                recommendation: $product->status === 'active'
                    ? 'No action needed.'
                    : 'Switch the product status to Active when you want shoppers and the chatbot to find it.',
            ),
            $this->check(
                key: 'category',
                label: 'Category readiness',
                state: match (true) {
                    ! $product->category => 'fail',
                    ! $product->category->is_active => 'warning',
                    default => 'pass',
                },
                reason: match (true) {
                    ! $product->category => 'This product is missing a category.',
                    ! $product->category->is_active => 'The assigned category is inactive, so category-led navigation may hide this product.',
                    default => 'The assigned category is active.',
                },
                recommendation: match (true) {
                    ! $product->category => 'Assign an active category so storefront filters and search have complete metadata.',
                    ! $product->category->is_active => 'Reactivate the category if this product should appear in storefront category navigation.',
                    default => 'No action needed.',
                },
            ),
            $this->check(
                key: 'variants',
                label: 'Active variants',
                state: $activeVariants->isNotEmpty() ? 'pass' : 'fail',
                reason: $activeVariants->isNotEmpty()
                    ? $activeVariants->count().' active variant(s) are available for pricing and stock checks.'
                    : 'This product has no active variants.',
                recommendation: $activeVariants->isNotEmpty()
                    ? 'No action needed.'
                    : 'Add at least one active size or color variant so the product can be sold.',
            ),
            $this->check(
                key: 'pricing',
                label: 'Variant pricing',
                state: $hasPrice ? 'pass' : 'fail',
                reason: $hasPrice
                    ? 'At least one active variant has a selling price.'
                    : 'No active variant currently has a selling price.',
                recommendation: $hasPrice
                    ? 'No action needed.'
                    : 'Set a selling price on at least one active variant.',
            ),
            $this->check(
                key: 'stock',
                label: 'Stock or backorder',
                state: match ($availability['state'] ?? null) {
                    ProductAvailabilityService::STATE_IN_STOCK,
                    ProductAvailabilityService::STATE_LOW_STOCK,
                    ProductAvailabilityService::STATE_BACKORDER => 'pass',
                    ProductAvailabilityService::STATE_INACTIVE => 'fail',
                    default => 'warning',
                },
                reason: match ($availability['state'] ?? null) {
                    ProductAvailabilityService::STATE_IN_STOCK => 'Active variants have stock on hand.',
                    ProductAvailabilityService::STATE_LOW_STOCK => 'Active variants are sellable, but stock is at or below the low-stock threshold.',
                    ProductAvailabilityService::STATE_BACKORDER => 'Active variants can still be sold because backorder is enabled.',
                    ProductAvailabilityService::STATE_INACTIVE => 'Availability is inactive because the product or its variants are not active.',
                    default => 'Active variants have no stock and backorder is disabled.',
                },
                recommendation: match ($availability['state'] ?? null) {
                    ProductAvailabilityService::STATE_IN_STOCK,
                    ProductAvailabilityService::STATE_LOW_STOCK,
                    ProductAvailabilityService::STATE_BACKORDER => 'No action needed.',
                    ProductAvailabilityService::STATE_INACTIVE => 'Activate the product and at least one variant to restore storefront availability.',
                    default => 'Add stock or enable backorder on an active variant.',
                },
            ),
            $this->buildImageCheck($primaryImageValue, $resolvedImageUrl, $localRelativeImage, $localImagePath, $externalImage),
            $this->check(
                key: 'storefront',
                label: 'Storefront visibility',
                state: $storefrontVisible ? 'pass' : 'fail',
                reason: $storefrontVisible
                    ? 'The storefront availability rules currently allow this product to appear.'
                    : 'The storefront availability rules are currently excluding this product.',
                recommendation: $storefrontVisible
                    ? 'No action needed.'
                    : $this->primaryVisibilityRecommendation($product, $availability, $activeVariants, $hasPrice),
            ),
            $this->check(
                key: 'chatbot',
                label: 'Chatbot discoverability',
                state: $chatbotDiscoverable ? 'pass' : ($hasSearchableMetadata ? 'warning' : 'fail'),
                reason: $chatbotDiscoverable
                    ? 'Chatbot product discovery can use this product because it is searchable and storefront-eligible.'
                    : ($hasSearchableMetadata
                        ? 'Chatbot discovery follows storefront visibility rules, so this product is currently filtered out.'
                        : 'This product is missing searchable name or category metadata.'),
                recommendation: $chatbotDiscoverable
                    ? 'No action needed.'
                    : ($hasSearchableMetadata
                        ? $this->primaryVisibilityRecommendation($product, $availability, $activeVariants, $hasPrice)
                        : 'Complete the product name and category so discovery services can index it safely.'),
            ),
            $this->check(
                key: 'visual_search_index',
                label: 'Visual search indexing',
                state: $visualEntries->isNotEmpty() ? 'pass' : 'warning',
                reason: $visualEntries->isNotEmpty()
                    ? $visualEntries->count().' visual search image entr'.($visualEntries->count() === 1 ? 'y is' : 'ies are').' present for this product.'
                    : 'This product does not have a visual search index entry yet.',
                recommendation: $visualEntries->isNotEmpty()
                    ? 'No action needed unless the image has changed.'
                    : 'Save or update the product image again after visual search health is restored so the targeted image sync can retry automatically.',
            ),
            $this->check(
                key: 'visual_search_stale',
                label: 'Visual search freshness',
                state: $staleVisualEntries > 0 ? 'warning' : 'pass',
                reason: $staleVisualEntries > 0
                    ? 'The indexed visual search image is older than the current product image.'
                    : 'The visual search image index is up to date for this product.',
                recommendation: $staleVisualEntries > 0
                    ? 'Save the product image again or restore visual search health so the targeted sync can refresh this product entry.'
                    : 'No action needed.',
            ),
            $this->check(
                key: 'metadata',
                label: 'Searchable metadata',
                state: $hasSearchableMetadata ? 'pass' : 'fail',
                reason: $hasSearchableMetadata
                    ? 'Product name and category metadata are present for storefront and assistant search.'
                    : 'Product name or category metadata is incomplete.',
                recommendation: $hasSearchableMetadata
                    ? 'No action needed.'
                    : 'Complete the product name and category fields.',
            ),
        ];

        return [
            'storefront_visible' => $storefrontVisible,
            'chatbot_discoverable' => $chatbotDiscoverable,
            'resolved_image_url' => $resolvedImageUrl,
            'availability' => $availability,
            'checks' => $checks,
            'primary_issue' => $this->primaryIssueMessage($product, $availability, $activeVariants, $hasPrice),
        ];
    }

    public function primaryIssueMessage(Product $product, ?array $availability = null, ?Collection $activeVariants = null, ?bool $hasPrice = null): ?string
    {
        $product->loadMissing(['variants.inventoryItem']);
        $availability ??= $this->availability->forProduct($product);
        $activeVariants ??= $product->variants
            ->filter(fn (ProductVariant $variant): bool => $variant->status === 'active')
            ->values();
        $hasPrice ??= $activeVariants
            ->contains(fn (ProductVariant $variant): bool => $variant->price !== null);

        if ($product->status !== 'active') {
            return 'product status is '.ucfirst((string) $product->status);
        }

        if (($activeVariants?->isEmpty() ?? true) === true) {
            return 'there are no active variants';
        }

        if (($hasPrice ?? false) === false) {
            return 'no active variant has a selling price';
        }

        return match ($availability['state'] ?? null) {
            ProductAvailabilityService::STATE_SOLD_OUT => 'active variants have no stock or backorder',
            ProductAvailabilityService::STATE_INACTIVE => 'the current product availability is inactive',
            default => null,
        };
    }

    private function buildImageCheck(
        string $primaryImageValue,
        ?string $resolvedImageUrl,
        ?string $localRelativeImage,
        ?string $localImagePath,
        bool $externalImage,
    ): array {
        return match (true) {
            $resolvedImageUrl === null => $this->check(
                key: 'image',
                label: 'Primary image',
                state: 'warning',
                reason: 'No resolvable primary image is available, so the admin preview and storefront will fall back to a placeholder.',
                recommendation: 'Upload a primary image or provide a valid image path or URL.',
            ),
            $localRelativeImage !== null && $localImagePath === null => $this->check(
                key: 'image',
                label: 'Primary image',
                state: 'warning',
                reason: 'The current image path resolves locally, but the file is missing from public storage.',
                recommendation: 'Upload a replacement image or fix the stored path.',
            ),
            $externalImage => $this->check(
                key: 'image',
                label: 'Primary image',
                state: 'warning',
                reason: 'An external image URL is present. The browser preview depends on that remote source staying available.',
                recommendation: 'Use the built-in upload flow if you want fully managed local storage.',
            ),
            $primaryImageValue !== '' => $this->check(
                key: 'image',
                label: 'Primary image',
                state: 'pass',
                reason: 'A resolved primary image is available for admin and storefront previews.',
                recommendation: 'No action needed.',
            ),
            default => $this->check(
                key: 'image',
                label: 'Primary image',
                state: 'warning',
                reason: 'A fallback image is available, but there is no primary image path stored on the product itself.',
                recommendation: 'Save a primary image if you want visual search and admin previews to track it directly.',
            ),
        };
    }

    private function primaryVisibilityRecommendation(
        Product $product,
        array $availability,
        Collection $activeVariants,
        bool $hasPrice,
    ): string {
        return match (true) {
            $product->status !== 'active' => 'Switch the product status to Active.',
            $activeVariants->isEmpty() => 'Add at least one active variant.',
            ! $hasPrice => 'Set a selling price on at least one active variant.',
            ($availability['state'] ?? null) === ProductAvailabilityService::STATE_SOLD_OUT => 'Add stock or enable backorder on an active variant.',
            default => 'Review the checks above to restore storefront visibility.',
        };
    }

    private function check(string $key, string $label, string $state, string $reason, string $recommendation): array
    {
        return compact('key', 'label', 'state', 'reason', 'recommendation');
    }

    private function isLocalHostUrl(string $value): bool
    {
        $host = strtolower((string) parse_url($value, PHP_URL_HOST));
        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        return $host !== '' && in_array($host, array_filter([$appHost, 'localhost', '127.0.0.1']), true);
    }

    private function visualEntriesFor(Product $product): Collection
    {
        if (! $this->visualIndexTableExists()) {
            return collect();
        }

        return VisualSearchIndexEntry::query()
            ->where('product_id', $product->id)
            ->get();
    }

    private function visualIndexTableExists(): bool
    {
        try {
            return Schema::hasTable('visual_search_index_entries');
        } catch (\Throwable) {
            return false;
        }
    }
}
