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

    public const STATE_SOLD_OUT = 'sold_out';

    public const STATE_INACTIVE = 'inactive';

    public const STATE_BACKORDER = 'available_for_backorder';

    public function forProduct(Product $product): array
    {
        $this->loadProductRelations($product);

        $isActive = $product->status === 'active';
        $inventoryTracked = (bool) $product->track_inventory;
        $activeVariants = $this->activeVariantsForProduct($product);
        $variantAvailability = $activeVariants
            ->map(fn (ProductVariant $variant): array => $this->forVariant($variant));

        $sellableVariants = $variantAvailability
            ->filter(fn (array $availability): bool => in_array($availability['state'], [
                self::STATE_IN_STOCK,
                self::STATE_LOW_STOCK,
                self::STATE_BACKORDER,
            ], true))
            ->values();

        $availableQuantity = $inventoryTracked
            ? (int) $variantAvailability->sum(fn (array $availability): int => (int) ($availability['available_quantity'] ?? 0))
            : null;
        $aggregateReorderLevel = $inventoryTracked
            ? (int) $variantAvailability->sum(fn (array $availability): int => (int) ($availability['reorder_level'] ?? 0))
            : null;
        $hasBackorderVariant = $variantAvailability->contains(
            fn (array $availability): bool => ($availability['available_for_backorder'] ?? false) === true
        );

        $state = match (true) {
            ! $isActive => self::STATE_INACTIVE,
            ! $inventoryTracked => self::STATE_IN_STOCK,
            $availableQuantity > 0 && $aggregateReorderLevel > 0 && $availableQuantity <= $aggregateReorderLevel => self::STATE_LOW_STOCK,
            $availableQuantity > 0 => self::STATE_IN_STOCK,
            $hasBackorderVariant => self::STATE_BACKORDER,
            default => self::STATE_SOLD_OUT,
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
            'sold_out' => $state === self::STATE_SOLD_OUT,
            'available_for_backorder' => $state === self::STATE_BACKORDER,
            'allows_backorder' => $hasBackorderVariant,
            'reorder_level' => $aggregateReorderLevel,
            'available_sizes' => $this->sortedOptionValues($sellableVariants, 'size'),
            'available_colors' => $this->sortedOptionValues($sellableVariants, 'color'),
            'all_sizes' => $this->sortedOptionValues($variantAvailability, 'size'),
            'all_colors' => $this->sortedOptionValues($variantAvailability, 'color'),
            'variant_count' => $activeVariants->count(),
            'is_discoverable' => $isActive && in_array($state, [
                self::STATE_IN_STOCK,
                self::STATE_LOW_STOCK,
                self::STATE_BACKORDER,
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
            $availableQuantity > 0 && $reorderLevel > 0 && $availableQuantity <= $reorderLevel => self::STATE_LOW_STOCK,
            $availableQuantity > 0 => self::STATE_IN_STOCK,
            $allowBackorder => self::STATE_BACKORDER,
            default => self::STATE_SOLD_OUT,
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
            'sold_out' => $state === self::STATE_SOLD_OUT,
            'available_for_backorder' => $state === self::STATE_BACKORDER,
            'allow_backorder' => $allowBackorder,
            'reorder_state' => $this->reorderStateFor($state, $inventoryTracked),
            'size' => $this->normalizeSize((string) data_get($variant->option_values, 'size', '')),
            'color' => $this->normalizeOptionValue((string) data_get($variant->option_values, 'color', '')),
        ];
    }

    public function forProductSize(Product $product, string $size, ?string $color = null): array
    {
        $this->loadProductRelations($product);

        $requestedSize = $this->normalizeSize($size);
        $requestedColor = $this->normalizeOptionValue((string) $color);
        $productAvailability = $this->forProduct($product);
        $activeVariants = $this->activeVariantsForProduct($product);
        $matchingSizeVariants = $activeVariants
            ->filter(fn (ProductVariant $variant): bool => $this->normalizeSize((string) data_get($variant->option_values, 'size', '')) === $requestedSize)
            ->values();
        $matchingVariants = $requestedColor === ''
            ? $matchingSizeVariants
            : $matchingSizeVariants->filter(function (ProductVariant $variant) use ($requestedColor): bool {
                $variantColor = Str::lower((string) data_get($variant->option_values, 'color', ''));

                return $variantColor !== '' && str_contains($variantColor, $requestedColor);
            })->values();
        $matchingAvailability = $matchingVariants
            ->map(fn (ProductVariant $variant): array => $this->forVariant($variant))
            ->values();
        $availableQuantity = (int) $matchingAvailability->sum(fn (array $availability): int => (int) ($availability['available_quantity'] ?? 0));
        $hasBackorder = $matchingAvailability->contains(
            fn (array $availability): bool => ($availability['available_for_backorder'] ?? false) === true
        );
        $state = match (true) {
            ! $productAvailability['is_active'] => self::STATE_INACTIVE,
            $availableQuantity > 0 && $matchingAvailability->contains(fn (array $availability): bool => ($availability['low_stock'] ?? false) === true) => self::STATE_LOW_STOCK,
            $availableQuantity > 0 => self::STATE_IN_STOCK,
            $hasBackorder => self::STATE_BACKORDER,
            $matchingVariants->isNotEmpty() => self::STATE_SOLD_OUT,
            default => self::STATE_INACTIVE,
        };

        return [
            'product_id' => $product->id,
            'requested_size' => $requestedSize,
            'requested_color' => $requestedColor !== '' ? $requestedColor : null,
            'has_size' => $matchingSizeVariants->isNotEmpty(),
            'has_color' => $requestedColor === '' ? null : $matchingVariants->isNotEmpty(),
            'has_variant' => $matchingVariants->isNotEmpty(),
            'is_available' => $availableQuantity > 0,
            'is_backorder' => $hasBackorder,
            'is_low_stock' => $state === self::STATE_LOW_STOCK,
            'available_quantity' => $availableQuantity,
            'state' => $state,
            'label' => $this->labelForState($state),
            'available_sizes' => $productAvailability['available_sizes'],
            'available_colors' => $productAvailability['available_colors'],
        ];
    }

    public function isDiscoverable(Product $product): bool
    {
        return (bool) ($this->forProduct($product)['is_discoverable'] ?? false);
    }

    public function labelForState(string $state): string
    {
        return match ($state) {
            self::STATE_IN_STOCK => 'In Stock',
            self::STATE_LOW_STOCK => 'Low Stock',
            self::STATE_BACKORDER => 'Available for Backorder',
            self::STATE_INACTIVE => 'Inactive',
            default => 'Sold Out',
        };
    }

    private function activeVariantsForProduct(Product $product): Collection
    {
        return $product->variants
            ->filter(fn (ProductVariant $variant): bool => $variant->status === 'active')
            ->values();
    }

    private function sortedOptionValues(Collection $items, string $key): array
    {
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
        $product->loadMissing(['variants.inventoryItem']);
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

    private function reorderStateFor(string $state, bool $inventoryTracked): string
    {
        if (! $inventoryTracked) {
            return 'not_tracked';
        }

        return match ($state) {
            self::STATE_LOW_STOCK => 'low_stock',
            self::STATE_BACKORDER => 'backorder',
            self::STATE_SOLD_OUT => 'sold_out',
            self::STATE_INACTIVE => 'inactive',
            default => 'healthy',
        };
    }

    private function sizeSortKey(string $value): float
    {
        $normalized = $this->normalizeSize($value);

        return is_numeric($normalized) ? (float) $normalized : PHP_FLOAT_MAX;
    }
}
