<?php

namespace App\Services\Admin;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Models\User;
use App\Services\Inventory\InventoryManager;
use App\Support\Admin\InventoryMovementType;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductUpsertService
{
    public function __construct(
        private readonly InventoryManager $inventoryManager,
    ) {}

    public function store(array $payload, User $actor): Product
    {
        return DB::transaction(fn (): Product => $this->persist(new Product, $payload, $actor));
    }

    public function update(Product $product, array $payload, User $actor): Product
    {
        return DB::transaction(fn (): Product => $this->persist($product, $payload, $actor));
    }

    private function persist(Product $product, array $payload, User $actor): Product
    {
        $productData = Arr::except($payload, ['variants']);
        $productData['slug'] = $productData['slug'] ?: Str::slug($productData['name']);

        $variants = collect($payload['variants'])
            ->map(function (array $variant): array {
                $variant['option_values'] = array_filter([
                    'size' => $variant['size'] ?: null,
                    'color' => $variant['color'] ?: null,
                ]);

                return $variant;
            })
            ->values();

        $productData['base_price'] = (float) $variants->min('price');
        $productData['compare_at_price'] = $variants->pluck('compare_at_price')->filter()->min();

        $product->fill($productData);
        $product->save();

        $existingIds = [];

        foreach ($variants as $variantData) {
            $isExistingVariant = (bool) $variantData['id'];
            $variant = $isExistingVariant
                ? $product->variants()->whereKey($variantData['id'])->firstOrFail()
                : $product->variants()->make();

            $variant->fill([
                'name' => $variantData['name'],
                'sku' => $variantData['sku'],
                'barcode' => $variantData['barcode'] ?: null,
                'option_values' => $variantData['option_values'],
                'price' => $variantData['price'],
                'compare_at_price' => $variantData['compare_at_price'] ?: null,
                'cost_price' => $variantData['cost_price'] ?: null,
                'supplier_name' => $variantData['supplier_name'] ?: null,
                'weight_grams' => $variantData['weight_grams'] ?: null,
                'status' => $variantData['status'],
            ]);
            $variant->save();

            $inventoryItem = $variant->inventoryItem()->firstOrCreate([], [
                'quantity_on_hand' => 0,
                'reserved_quantity' => 0,
                'reorder_level' => 0,
                'allow_backorder' => false,
            ]);

            $currentQuantity = (int) $inventoryItem->quantity_on_hand;
            $targetQuantity = (int) $variantData['quantity_on_hand'];

            $inventoryItem->forceFill([
                'reorder_level' => $variantData['reorder_level'],
                'allow_backorder' => $variantData['allow_backorder'],
            ])->save();

            $this->syncAuditedInventoryLevel(
                variant: $variant,
                currentQuantity: $currentQuantity,
                targetQuantity: $targetQuantity,
                actor: $actor,
                isExistingVariant: $isExistingVariant,
            );

            $existingIds[] = $variant->id;
        }

        $product->variants()
            ->whereNotIn('id', $existingIds)
            ->get()
            ->each(function (ProductVariant $variant): void {
                $inventoryQuantity = (int) ($variant->inventoryItem?->quantity_on_hand ?? 0);
                $hasMovementHistory = $variant->stockMovements()->exists();

                if ($inventoryQuantity > 0 || $hasMovementHistory) {
                    $variant->forceFill(['status' => 'archived'])->save();

                    return;
                }

                $variant->inventoryItem()?->delete();
                $variant->delete();
            });

        return $product->fresh(['category', 'variants.inventoryItem']);
    }

    private function syncAuditedInventoryLevel(
        ProductVariant $variant,
        int $currentQuantity,
        int $targetQuantity,
        User $actor,
        bool $isExistingVariant,
    ): void {
        if ($targetQuantity === $currentQuantity) {
            return;
        }

        $this->inventoryManager->recordManualChange(
            variant: $variant,
            quantity: $isExistingVariant ? $targetQuantity - $currentQuantity : $targetQuantity,
            type: $isExistingVariant ? InventoryMovementType::ADJUSTMENT : InventoryMovementType::STOCK_IN,
            actor: $actor,
            referenceNumber: 'CATALOG-'.$variant->product_id,
            notes: $isExistingVariant
                ? 'Inventory adjusted from the product maintenance form.'
                : 'Opening stock recorded when the product variant was created.',
            metadata: [
                'origin' => 'product_form',
                'previous_quantity' => $currentQuantity,
                'target_quantity' => $targetQuantity,
            ],
        );
    }
}
