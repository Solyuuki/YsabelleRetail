<?php

namespace Database\Seeders\Catalog;

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use Database\Seeders\Catalog\Support\CatalogBlueprint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach (CatalogBlueprint::categories() as $categoryData) {
            $category = Category::query()->updateOrCreate(
                ['slug' => $categoryData['slug']],
                [
                    'name' => $categoryData['name'],
                    'description' => $categoryData['description'],
                    'is_active' => true,
                    'sort_order' => $categoryData['sort_order'],
                ]
            );

            foreach ($categoryData['products'] as $productData) {
                $product = Product::query()->updateOrCreate(
                    ['slug' => Str::slug($productData['name'])],
                    [
                        'category_id' => $category->id,
                        'name' => $productData['name'],
                        'style_code' => $productData['style_code'],
                        'short_description' => $productData['short_description'],
                        'description' => $productData['description'],
                        'primary_image_url' => $productData['primary_image_url'],
                        'image_alt' => $productData['image_alt'],
                        'image_gallery' => $productData['image_gallery'],
                        'base_price' => $productData['base_price'],
                        'compare_at_price' => $productData['compare_at_price'],
                        'rating_average' => $productData['rating_average'],
                        'review_count' => $productData['review_count'],
                        'status' => 'active',
                        'is_featured' => $productData['is_featured'],
                        'featured_rank' => $productData['featured_rank'],
                        'track_inventory' => true,
                    ]
                );

                foreach ($productData['variants'] as $variantData) {
                    $variant = ProductVariant::query()->updateOrCreate(
                        ['sku' => $variantData['sku']],
                        [
                            'product_id' => $product->id,
                            'name' => $variantData['name'],
                            'barcode' => $variantData['barcode'],
                            'option_values' => $variantData['option_values'],
                            'price' => $variantData['price'],
                            'compare_at_price' => $variantData['compare_at_price'],
                            'cost_price' => $variantData['cost_price'],
                            'supplier_name' => $variantData['supplier_name'],
                            'weight_grams' => $variantData['weight_grams'],
                            'status' => $variantData['status'],
                        ]
                    );

                    $variant->inventoryItem()->updateOrCreate(
                        ['product_variant_id' => $variant->id],
                        $variantData['inventory'],
                    );
                }
            }
        }
    }
}
