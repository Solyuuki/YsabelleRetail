<?php

namespace App\Services\Catalog;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProductAvailabilityService
{
    public const STATE_IN_STOCK = 'in_stock';

    public const STATE_LOW_STOCK = 'low_stock';

    public const STATE_OUT_OF_STOCK = 'out_of_stock';

    public const STATE_INACTIVE = 'inactive';

    public const STATE_BACKORDER_AVAILABLE = 'backorder_available';

    public const STATE_SOLD_OUT = self::STATE_OUT_OF_STOCK;

    public const STATE_BACKORDER = self::STATE_BACKORDER_AVAILABLE;

    public function forProduct(Product $product): array
    {
        $this->loadProductRelations($product);

        $isActive = $product->status === 'active';
        $inventoryTracked = (bool) $product->track_inventory;
        $activeVariants = $this->activeVariantsForProduct($product);
        $variantEntries = $this->variantEntriesFor($activeVariants);
        $variantAvailability = $variantEntries->pluck('availability')->values();
        $variantOptions = $this->variantOptionsFromEntries($variantEntries)->values();
        $colorOptions = $this->colorOptionsFromVariantOptions($variantOptions)->values();
        $defaultColorOption = $this->defaultColorOption($colorOptions);
        $sizeOptions = collect($defaultColorOption['size_options'] ?? [])->values();
        $sellableSizeOptions = $sizeOptions
            ->filter(fn (array $option): bool => ($option['is_selectable'] ?? false) === true)
            ->values();
        $availableQuantity = $inventoryTracked
            ? (int) $variantAvailability->sum(fn (array $availability): int => (int) ($availability['available_quantity'] ?? 0))
            : null;
        $hasInStockVariant = $variantAvailability->contains(
            fn (array $availability): bool => ($availability['state'] ?? null) === self::STATE_IN_STOCK
        );
        $hasLowStockVariant = $variantAvailability->contains(
            fn (array $availability): bool => ($availability['state'] ?? null) === self::STATE_LOW_STOCK
        );
        $hasBackorderVariant = $variantAvailability->contains(
            fn (array $availability): bool => ($availability['state'] ?? null) === self::STATE_BACKORDER_AVAILABLE
        );
        $aggregateReorderLevel = $inventoryTracked
            ? (int) $variantAvailability->sum(fn (array $availability): int => (int) ($availability['reorder_level'] ?? 0))
            : null;

        $state = match (true) {
            ! $isActive => self::STATE_INACTIVE,
            ! $inventoryTracked => self::STATE_IN_STOCK,
            $hasInStockVariant => self::STATE_IN_STOCK,
            $hasLowStockVariant => self::STATE_LOW_STOCK,
            $hasBackorderVariant => self::STATE_BACKORDER_AVAILABLE,
            default => self::STATE_OUT_OF_STOCK,
        };

        return [
            'product_id' => $product->id,
            'is_active' => $isActive,
            'inactive' => ! $isActive,
            'inventory_tracked' => $inventoryTracked,
            'state' => $state,
            'label' => $this->labelForState($state),
            'quantity' => $availableQuantity,
            'available_quantity' => $availableQuantity,
            'total_available_quantity' => $availableQuantity,
            'low_stock' => $state === self::STATE_LOW_STOCK,
            'out_of_stock' => $state === self::STATE_OUT_OF_STOCK,
            'sold_out' => $state === self::STATE_OUT_OF_STOCK,
            'available_for_backorder' => $state === self::STATE_BACKORDER_AVAILABLE,
            'backorder_available' => $state === self::STATE_BACKORDER_AVAILABLE,
            'allows_backorder' => $hasBackorderVariant,
            'has_low_stock_variants' => $hasLowStockVariant,
            'reorder_level' => $aggregateReorderLevel,
            'available_sizes' => $this->sortedOptionValues($sellableSizeOptions, 'size'),
            'available_colors' => $this->sortedOptionValues($colorOptions, 'color_label', fn (array $option): bool => ($option['has_selectable_sizes'] ?? false) === true),
            'all_sizes' => $this->sortedOptionValues($sizeOptions, 'size'),
            'all_colors' => $this->sortedOptionValues($colorOptions, 'color_label'),
            'variant_count' => $activeVariants->count(),
            'variant_options' => $variantOptions->values()->all(),
            'color_options' => $colorOptions->values()->all(),
            'size_options' => $sizeOptions->values()->all(),
            'default_color' => $defaultColorOption['color_key'] ?? null,
            'default_color_label' => $defaultColorOption['color_label'] ?? null,
            'is_discoverable' => $isActive && in_array($state, [
                self::STATE_IN_STOCK,
                self::STATE_LOW_STOCK,
                self::STATE_BACKORDER_AVAILABLE,
            ], true),
        ];
    }

    public function forVariant(ProductVariant $variant): array
    {
        $variant->loadMissing(['product', 'inventoryItem']);

        $product = $variant->product;
        $productActive = $product?->status === 'active';
        $variantActive = $variant->status === 'active';
        $isActive = $productActive && $variantActive;
        $inventoryTracked = (bool) ($product?->track_inventory ?? true);
        $inventoryItem = $variant->inventoryItem;
        $availableQuantity = $inventoryTracked ? (int) ($inventoryItem?->available_quantity ?? 0) : null;
        $quantityOnHand = $inventoryTracked ? (int) ($inventoryItem?->quantity_on_hand ?? 0) : null;
        $reservedQuantity = $inventoryTracked ? (int) ($inventoryItem?->reserved_quantity ?? 0) : null;
        $reorderLevel = $inventoryTracked ? max(0, (int) ($inventoryItem?->reorder_level ?? 0)) : null;
        $allowBackorder = $inventoryTracked ? (bool) ($inventoryItem?->allow_backorder ?? false) : false;

        $state = match (true) {
            ! $isActive => self::STATE_INACTIVE,
            ! $inventoryTracked => self::STATE_IN_STOCK,
            $availableQuantity <= 0 && $allowBackorder => self::STATE_BACKORDER_AVAILABLE,
            $availableQuantity <= 0 => self::STATE_OUT_OF_STOCK,
            $reorderLevel > 0 && $availableQuantity <= $reorderLevel => self::STATE_LOW_STOCK,
            default => self::STATE_IN_STOCK,
        };

        return [
            'variant_id' => $variant->id,
            'product_id' => $variant->product_id,
            'is_active' => $isActive,
            'inactive' => ! $isActive,
            'inventory_tracked' => $inventoryTracked,
            'state' => $state,
            'label' => $this->labelForState($state),
            'available_quantity' => $availableQuantity,
            'quantity_on_hand' => $quantityOnHand,
            'reserved_quantity' => $reservedQuantity,
            'reorder_level' => $reorderLevel,
            'low_stock' => $state === self::STATE_LOW_STOCK,
            'out_of_stock' => $state === self::STATE_OUT_OF_STOCK,
            'sold_out' => $state === self::STATE_OUT_OF_STOCK,
            'available_for_backorder' => $state === self::STATE_BACKORDER_AVAILABLE,
            'backorder_available' => $state === self::STATE_BACKORDER_AVAILABLE,
            'allow_backorder' => $allowBackorder,
            'reorder_state' => $this->reorderStateFor($state, $inventoryTracked),
            'size' => $this->normalizeSize((string) data_get($variant->option_values, 'size', '')),
            'size_label' => $this->displaySize((string) data_get($variant->option_values, 'size', '')),
            'color' => $this->normalizeOptionValue((string) data_get($variant->option_values, 'color', '')),
            'color_label' => $this->displayColor((string) data_get($variant->option_values, 'color', '')),
            'is_selectable' => $this->isSelectableState($state),
            'is_purchasable' => $this->isSelectableState($state),
        ];
    }

    public function forProductSize(Product $product, string $size, ?string $color = null): array
    {
        $this->loadProductRelations($product);

        $requestedSize = $this->normalizeSize($size);
        $requestedColor = trim((string) $color);
        $resolvedColor = $this->resolveColorOptionForProduct($product, $requestedColor);
        $productAvailability = $this->forProduct($product);
        $variantOptions = collect($productAvailability['variant_options'] ?? []);
        $matchingSizeVariants = $variantOptions
            ->filter(fn (array $option): bool => ($option['size'] ?? null) === $requestedSize)
            ->values();
        $matchingVariants = $resolvedColor === null
            ? ($requestedColor === '' ? $matchingSizeVariants : collect())
            : $matchingSizeVariants
                ->filter(fn (array $option): bool => ($option['color'] ?? null) === ($resolvedColor['color_key'] ?? null))
                ->values();
        $matchingAvailability = $matchingVariants->values();
        $availableQuantity = (int) $matchingAvailability->sum(fn (array $availability): int => (int) ($availability['available_quantity'] ?? 0));
        $hasBackorder = $matchingAvailability->contains(
            fn (array $availability): bool => ($availability['state'] ?? null) === self::STATE_BACKORDER_AVAILABLE
        );
        $hasInStock = $matchingAvailability->contains(
            fn (array $availability): bool => ($availability['state'] ?? null) === self::STATE_IN_STOCK
        );
        $hasLowStock = $matchingAvailability->contains(
            fn (array $availability): bool => ($availability['state'] ?? null) === self::STATE_LOW_STOCK
        );
        $state = match (true) {
            ! $productAvailability['is_active'] => self::STATE_INACTIVE,
            $hasInStock => self::STATE_IN_STOCK,
            $hasLowStock => self::STATE_LOW_STOCK,
            $hasBackorder => self::STATE_BACKORDER_AVAILABLE,
            $matchingVariants->isNotEmpty() => self::STATE_OUT_OF_STOCK,
            default => self::STATE_INACTIVE,
        };

        return [
            'product_id' => $product->id,
            'requested_size' => $requestedSize,
            'requested_color' => $resolvedColor['color_key'] ?? ($requestedColor !== '' ? $this->normalizeOptionValue($requestedColor) : null),
            'requested_color_label' => $resolvedColor['color_label'] ?? ($requestedColor !== '' ? $this->displayColor($requestedColor) : null),
            'has_size' => $matchingSizeVariants->isNotEmpty(),
            'has_color' => $requestedColor === '' ? null : $resolvedColor !== null,
            'has_variant' => $matchingVariants->isNotEmpty(),
            'is_available' => in_array($state, [self::STATE_IN_STOCK, self::STATE_LOW_STOCK], true),
            'is_backorder' => $hasBackorder,
            'is_low_stock' => $state === self::STATE_LOW_STOCK,
            'available_quantity' => $availableQuantity,
            'state' => $state,
            'label' => $this->labelForState($state),
            'is_selectable' => $this->isSelectableState($state),
            'available_sizes' => $resolvedColor['available_sizes'] ?? $productAvailability['available_sizes'],
            'available_colors' => $productAvailability['available_colors'],
        ];
    }

    public function forRequestedQuantity(ProductVariant $variant, int $requestedQuantity): array
    {
        $availability = $this->forVariant($variant);
        $hasSufficientStock = ! ($availability['inventory_tracked'] ?? true)
            || ($availability['available_quantity'] ?? 0) >= $requestedQuantity
            || ($availability['allow_backorder'] ?? false) === true;
        $canPurchase = ($availability['is_purchasable'] ?? false) === true && $hasSufficientStock;
        $reason = match (true) {
            $requestedQuantity <= 0 => 'invalid_quantity',
            ($availability['state'] ?? null) === self::STATE_INACTIVE => 'inactive',
            ($availability['state'] ?? null) === self::STATE_OUT_OF_STOCK => 'out_of_stock',
            ! $hasSufficientStock => 'insufficient_stock',
            default => null,
        };

        return [
            ...$availability,
            'requested_quantity' => $requestedQuantity,
            'has_sufficient_stock' => $hasSufficientStock,
            'can_purchase' => $canPurchase,
            'has_issue' => ! $canPurchase,
            'reason' => $reason,
            'message' => $this->messageForReason($reason),
        ];
    }

    public function variantOptionsForProduct(Product $product): array
    {
        $this->loadProductRelations($product);

        return $this->variantOptionsFromEntries(
            $this->variantEntriesFor($this->activeVariantsForProduct($product))
        )->values()->all();
    }

    public function sizeOptionsForProduct(Product $product, ?string $color = null): array
    {
        $this->loadProductRelations($product);

        if (filled($color)) {
            $resolvedColor = $this->resolveColorOptionForProduct($product, $color);

            return collect($resolvedColor['size_options'] ?? [])
                ->values()
                ->all();
        }

        return collect($this->forProduct($product)['size_options'] ?? [])
            ->values()
            ->all();
    }

    public function colorOptionsForProduct(Product $product): array
    {
        $this->loadProductRelations($product);

        return $this->colorOptionsFromVariantOptions(
            collect($this->variantOptionsForProduct($product))
        )->values()->all();
    }

    public function resolveColorOptionForProduct(Product $product, ?string $color): ?array
    {
        $query = trim((string) $color);

        if ($query === '') {
            return null;
        }

        $queryKey = $this->normalizeOptionValue($query);
        $querySignature = $this->colorSignature($query);
        $queryTokens = $this->colorTokens($query);

        return collect($this->colorOptionsForProduct($product))
            ->sortByDesc(fn (array $option): int => strlen((string) ($option['color_label'] ?? $option['color'] ?? '')))
            ->first(function (array $option) use ($queryKey, $querySignature, $queryTokens): bool {
                $colorKey = (string) ($option['color_key'] ?? $option['color'] ?? '');
                $colorLabel = (string) ($option['color_label'] ?? $option['color'] ?? '');
                $labelSignature = $this->colorSignature($colorLabel);
                $labelTokens = $this->colorTokens($colorLabel);

                if ($colorKey === $queryKey || $this->normalizeOptionValue($colorLabel) === $queryKey) {
                    return true;
                }

                if ($querySignature !== '' && ($labelSignature === $querySignature || str_contains($labelSignature, $querySignature))) {
                    return true;
                }

                return $queryTokens !== []
                    && collect($queryTokens)->every(fn (string $token): bool => in_array($token, $labelTokens, true));
            });
    }

    public function findVariantOption(Product $product, int|string|null $variantId): ?array
    {
        $id = (int) $variantId;

        if ($id <= 0) {
            return null;
        }

        return collect($this->variantOptionsForProduct($product))
            ->first(fn (array $option): bool => (int) ($option['variant_id'] ?? 0) === $id);
    }

    public function variantOptionsForProductSize(Product $product, string $size): array
    {
        $requestedSize = $this->normalizeSize($size);

        return collect($this->variantOptionsForProduct($product))
            ->filter(fn (array $option): bool => ($option['size'] ?? null) === $requestedSize)
            ->values()
            ->all();
    }

    public function isDiscoverable(Product $product): bool
    {
        return (bool) ($this->forProduct($product)['is_discoverable'] ?? false);
    }

    public function labelForState(string $state): string
    {
        return match ($state) {
            self::STATE_IN_STOCK => 'In Stock',
            self::STATE_LOW_STOCK => 'Limited Stock',
            self::STATE_BACKORDER_AVAILABLE => 'Backorder Available',
            default => 'Currently Unavailable',
        };
    }

    private function activeVariantsForProduct(Product $product): Collection
    {
        return $product->variants
            ->filter(fn (ProductVariant $variant): bool => $variant->status === 'active')
            ->values();
    }

    private function sortedOptionValues(Collection $items, string $key, ?callable $filter = null): array
    {
        if ($filter) {
            $items = $items->filter($filter)->values();
        }

        return $items
            ->map(function (array $item) use ($key): ?string {
                $value = $item[$key] ?? null;

                if (! is_string($value) || trim($value) === '') {
                    return null;
                }

                return trim($value);
            })
            ->filter()
            ->unique()
            ->sortBy(fn (string $value): array => [$this->sizeSortKey($value), Str::lower($value)])
            ->values()
            ->all();
    }

    private function loadProductRelations(Product $product): void
    {
        $product->loadMissing(['variants.product', 'variants.inventoryItem']);
    }

    private function normalizeSize(string $size): string
    {
        $size = trim($size);

        if ($size === '' || ! str_contains($size, '.')) {
            return $size;
        }

        return rtrim(rtrim($size, '0'), '.');
    }

    private function normalizeOptionValue(string $value): string
    {
        return Str::lower(trim($value));
    }

    private function colorSignature(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/i', ' ')
            ->squish()
            ->value();
    }

    private function colorTokens(string $value): array
    {
        $signature = $this->colorSignature($value);

        if ($signature === '') {
            return [];
        }

        return array_values(array_filter(explode(' ', $signature)));
    }

    private function displaySize(string $size): string
    {
        return $this->normalizeSize($size);
    }

    private function displayColor(string $value): string
    {
        return trim($value);
    }

    private function reorderStateFor(string $state, bool $inventoryTracked): string
    {
        if (! $inventoryTracked) {
            return 'not_tracked';
        }

        return match ($state) {
            self::STATE_LOW_STOCK => 'low_stock',
            self::STATE_BACKORDER_AVAILABLE => 'backorder',
            self::STATE_OUT_OF_STOCK => 'out_of_stock',
            self::STATE_INACTIVE => 'inactive',
            default => 'healthy',
        };
    }

    private function sizeSortKey(string $value): float
    {
        $normalized = $this->normalizeSize($value);

        return is_numeric($normalized) ? (float) $normalized : PHP_FLOAT_MAX;
    }

    private function isSelectableState(string $state): bool
    {
        return in_array($state, [
            self::STATE_IN_STOCK,
            self::STATE_LOW_STOCK,
            self::STATE_BACKORDER_AVAILABLE,
        ], true);
    }

    private function messageForReason(?string $reason): ?string
    {
        return match ($reason) {
            'inactive' => 'This product is currently unavailable.',
            'insufficient_stock' => 'The requested quantity is no longer available.',
            'invalid_quantity' => 'Quantity must be greater than zero.',
            'out_of_stock' => 'This variant is currently unavailable.',
            default => null,
        };
    }

    private function variantEntriesFor(Collection $variants): Collection
    {
        return $variants
            ->map(function (ProductVariant $variant): array {
                $availability = $this->forVariant($variant);

                return [
                    'variant' => $variant,
                    'availability' => $availability,
                    'size' => $availability['size'],
                    'color' => $availability['color'],
                ];
            })
            ->values();
    }

    private function variantOptionsFromEntries(Collection $entries): Collection
    {
        return $entries
            ->groupBy(function (array $entry): string {
                return $this->optionSignature(
                    (string) ($entry['size'] ?? ''),
                    (string) ($entry['color'] ?? '')
                );
            })
            ->map(function (Collection $group): array {
                $primary = $this->bestVariantEntry($group);
                $variantIds = $group
                    ->map(fn (array $entry): int => (int) $entry['variant']->id)
                    ->values()
                    ->all();

                return [
                    ...$primary['availability'],
                    'variant_id' => (int) $primary['variant']->id,
                    'variant_ids' => $variantIds,
                    'duplicate_record_count' => count($variantIds),
                    'has_duplicates' => count($variantIds) > 1,
                    'size' => (string) ($primary['availability']['size'] ?? ''),
                    'size_label' => (string) ($primary['availability']['size_label'] ?? $primary['availability']['size'] ?? ''),
                    'color' => (string) ($primary['availability']['color'] ?? ''),
                    'color_label' => (string) ($primary['availability']['color_label'] ?? $primary['availability']['color'] ?? ''),
                ];
            })
            ->sortBy(function (array $option): array {
                return [
                    $this->sizeSortKey((string) ($option['size'] ?? '')),
                    Str::lower((string) ($option['color_label'] ?? $option['color'] ?? '')),
                    $this->statePriority((string) ($option['state'] ?? self::STATE_OUT_OF_STOCK)),
                ];
            })
            ->values();
    }

    private function colorOptionsFromVariantOptions(Collection $variantOptions): Collection
    {
        return $variantOptions
            ->groupBy(fn (array $option): string => (string) ($option['color'] ?? ''))
            ->map(function (Collection $group): array {
                $primary = $group
                    ->sort(function (array $left, array $right): int {
                        return [
                            $this->statePriority((string) ($left['state'] ?? self::STATE_OUT_OF_STOCK)),
                            -1 * (int) ($left['available_quantity'] ?? 0),
                            (int) ($left['variant_id'] ?? PHP_INT_MAX),
                        ]
                            <=>
                            [
                                $this->statePriority((string) ($right['state'] ?? self::STATE_OUT_OF_STOCK)),
                                -1 * (int) ($right['available_quantity'] ?? 0),
                                (int) ($right['variant_id'] ?? PHP_INT_MAX),
                            ];
                    })
                    ->first();

                return [
                    'color' => (string) ($primary['color'] ?? ''),
                    'color_key' => (string) ($primary['color'] ?? ''),
                    'color_label' => (string) ($primary['color_label'] ?? $primary['color'] ?? ''),
                    'state' => (string) ($primary['state'] ?? self::STATE_OUT_OF_STOCK),
                    'label' => $this->labelForState((string) ($primary['state'] ?? self::STATE_OUT_OF_STOCK)),
                    'is_selectable' => true,
                    'has_selectable_sizes' => $group->contains(fn (array $option): bool => ($option['is_selectable'] ?? false) === true),
                    'size_options' => $group
                        ->sortBy(fn (array $option): array => [$this->sizeSortKey((string) ($option['size'] ?? '')), Str::lower((string) ($option['size_label'] ?? $option['size'] ?? ''))])
                        ->values()
                        ->all(),
                    'available_sizes' => $group
                        ->filter(fn (array $option): bool => ($option['is_selectable'] ?? false) === true)
                        ->map(fn (array $option): string => (string) ($option['size_label'] ?? $option['size'] ?? ''))
                        ->values()
                        ->all(),
                    'all_sizes' => $group
                        ->map(fn (array $option): string => (string) ($option['size_label'] ?? $option['size'] ?? ''))
                        ->values()
                        ->all(),
                    'variant_count' => $group->count(),
                    'primary_variant_id' => (int) ($primary['variant_id'] ?? 0),
                ];
            })
            ->sortBy(fn (array $option): array => [Str::lower((string) ($option['color_label'] ?? '')), $this->statePriority((string) ($option['state'] ?? self::STATE_OUT_OF_STOCK))])
            ->values();
    }

    private function defaultColorOption(Collection $colorOptions): ?array
    {
        return $colorOptions
            ->sort(function (array $left, array $right): int {
                return [
                    ($left['has_selectable_sizes'] ?? false) ? 0 : 1,
                    $this->statePriority((string) ($left['state'] ?? self::STATE_OUT_OF_STOCK)),
                    Str::lower((string) ($left['color_label'] ?? '')),
                ]
                    <=>
                    [
                        ($right['has_selectable_sizes'] ?? false) ? 0 : 1,
                        $this->statePriority((string) ($right['state'] ?? self::STATE_OUT_OF_STOCK)),
                        Str::lower((string) ($right['color_label'] ?? '')),
                    ];
            })
            ->first();
    }

    private function sizeOptionsFromVariantOptions(Collection $variantOptions): Collection
    {
        return $variantOptions
            ->groupBy(fn (array $option): string => Str::lower((string) ($option['size'] ?? '')))
            ->map(function (Collection $group): array {
                $primary = $group
                    ->sort(function (array $left, array $right): int {
                        return [
                            $this->statePriority((string) ($left['state'] ?? self::STATE_OUT_OF_STOCK)),
                            -1 * (int) ($left['available_quantity'] ?? 0),
                            (int) ($left['variant_id'] ?? PHP_INT_MAX),
                        ]
                            <=>
                            [
                                $this->statePriority((string) ($right['state'] ?? self::STATE_OUT_OF_STOCK)),
                                -1 * (int) ($right['available_quantity'] ?? 0),
                                (int) ($right['variant_id'] ?? PHP_INT_MAX),
                            ];
                    })
                    ->first();
                $variantIds = $group
                    ->flatMap(fn (array $option): array => array_map('intval', $option['variant_ids'] ?? [$option['variant_id'] ?? 0]))
                    ->filter(fn (int $id): bool => $id > 0)
                    ->unique()
                    ->values()
                    ->all();

                return [
                    ...$primary,
                    'variant_ids' => $variantIds,
                    'record_count' => count($variantIds),
                    'has_duplicates' => count($variantIds) > 1,
                ];
            })
            ->sortBy(fn (array $option): array => [$this->sizeSortKey((string) ($option['size'] ?? '')), Str::lower((string) ($option['size'] ?? ''))])
            ->values();
    }

    private function bestVariantEntry(Collection $group): array
    {
        return $group
            ->sort(function (array $left, array $right): int {
                return [
                    $this->statePriority((string) ($left['availability']['state'] ?? self::STATE_OUT_OF_STOCK)),
                    -1 * (int) ($left['availability']['available_quantity'] ?? 0),
                    (int) $left['variant']->id,
                ]
                    <=>
                    [
                        $this->statePriority((string) ($right['availability']['state'] ?? self::STATE_OUT_OF_STOCK)),
                        -1 * (int) ($right['availability']['available_quantity'] ?? 0),
                        (int) $right['variant']->id,
                    ];
            })
            ->first();
    }

    private function optionSignature(string $size, string $color): string
    {
        return implode('|', [
            Str::lower(trim($size)),
            Str::lower(trim($color)),
        ]);
    }

    private function statePriority(string $state): int
    {
        return match ($state) {
            self::STATE_IN_STOCK => 0,
            self::STATE_LOW_STOCK => 1,
            self::STATE_BACKORDER_AVAILABLE => 2,
            self::STATE_OUT_OF_STOCK => 3,
            default => 4,
        };
    }
}
