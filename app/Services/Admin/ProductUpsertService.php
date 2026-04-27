<?php

namespace App\Services\Admin;

use App\Models\Catalog\Product;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductUpsertService
{
    public function store(array $payload): Product
    {
        return DB::transaction(fn (): Product => $this->persist(new Product(), $payload));
    }

    public function update(Product $product, array $payload): Product
    {
        return DB::transaction(fn (): Product => $this->persist($product, $payload));
    }

    private function persist(Product $product, array $payload): Product
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
            $variant = $variantData['id']
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

            $variant->inventoryItem()->updateOrCreate([], [
                'quantity_on_hand' => $variantData['quantity_on_hand'],
                'reserved_quantity' => 0,
                'reorder_level' => $variantData['reorder_level'],
                'allow_backorder' => $variantData['allow_backorder'],
            ]);

            $existingIds[] = $variant->id;
        }

        $product->variants()
            ->whereNotIn('id', $existingIds)
            ->get()
            ->each(function ($variant): void {
                $variant->inventoryItem()?->delete();
                $variant->delete();
            });

        return $product->fresh(['category', 'variants.inventoryItem']);
    }
}
