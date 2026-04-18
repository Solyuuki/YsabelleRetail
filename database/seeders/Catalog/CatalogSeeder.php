<?php

namespace Database\Seeders\Catalog;

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            'running' => [
                'name' => 'Running',
                'description' => 'Engineered pairs for movement, tempo, and long-mile comfort.',
                'products' => [
                    [
                        'name' => 'Aurum Runner',
                        'style_code' => 'YS-AUR-7490',
                        'base_price' => 7490,
                        'compare_at_price' => 8990,
                        'rating_average' => 4.9,
                        'review_count' => 184,
                        'is_featured' => true,
                        'short_description' => 'Featherlight performance runner with carbon-infused energy return.',
                        'description' => 'Featherlight performance runner crafted with breathable engineered mesh and a responsive carbon-infused midsole. Built for distance, designed for legacy.',
                        'sizes' => ['7', '8', '9', '10', '11', '12'],
                    ],
                    [
                        'name' => 'Shadow Stride',
                        'style_code' => 'YS-SHD-6490',
                        'base_price' => 6490,
                        'compare_at_price' => null,
                        'rating_average' => 4.7,
                        'review_count' => 96,
                        'is_featured' => true,
                        'short_description' => 'Stealth silhouette with cushioned comfort for everyday mileage.',
                        'description' => 'A minimalist runner tuned for daily training with soft underfoot support, tonal detailing, and a low-profile silhouette.',
                        'sizes' => ['7', '8', '9', '10', '11'],
                    ],
                    [
                        'name' => 'Azure Velocity',
                        'style_code' => 'YS-AZV-5790',
                        'base_price' => 5790,
                        'compare_at_price' => null,
                        'rating_average' => 4.5,
                        'review_count' => 73,
                        'is_featured' => false,
                        'short_description' => 'Electric pace trainer with a cool-toned performance finish.',
                        'description' => 'A lively everyday pair with lightweight support, visual contrast, and a race-inspired stance for modern city runs.',
                        'sizes' => ['7', '8', '9', '10'],
                    ],
                ],
            ],
            'sneakers' => [
                'name' => 'Sneakers',
                'description' => 'Premium casual silhouettes for refined off-duty dressing.',
                'products' => [
                    [
                        'name' => 'Ivory Prestige',
                        'style_code' => 'YS-IVR-5890',
                        'base_price' => 5890,
                        'compare_at_price' => null,
                        'rating_average' => 4.8,
                        'review_count' => 121,
                        'is_featured' => true,
                        'short_description' => 'Clean court-inspired sneaker finished with warm metallic restraint.',
                        'description' => 'An elevated lifestyle silhouette with bright leather, subtle metallic detailing, and a polished low-top stance.',
                        'sizes' => ['6', '7', '8', '9', '10'],
                    ],
                ],
            ],
            'sport' => [
                'name' => 'Sport',
                'description' => 'Technical support shoes for active everyday movement.',
                'products' => [
                    [
                        'name' => 'Volt Edge',
                        'style_code' => 'YS-VLT-5790',
                        'base_price' => 5790,
                        'compare_at_price' => 6690,
                        'rating_average' => 4.5,
                        'review_count' => 88,
                        'is_featured' => true,
                        'short_description' => 'Sport utility pair with visible performance grip and neon edge.',
                        'description' => 'A responsive sport silhouette with high-contrast edge detailing, stable cushioning, and everyday support.',
                        'sizes' => ['7', '8', '9', '10', '11'],
                    ],
                ],
            ],
            'performance' => [
                'name' => 'Performance',
                'description' => 'Precision-built footwear for customers chasing premium performance.',
                'products' => [],
            ],
        ];

        foreach ($catalog as $slug => $categoryData) {
            $category = Category::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $categoryData['name'],
                    'description' => $categoryData['description'],
                    'is_active' => true,
                    'sort_order' => match ($slug) {
                        'running' => 1,
                        'sneakers' => 2,
                        'sport' => 3,
                        default => 4,
                    },
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
                        'base_price' => $productData['base_price'],
                        'compare_at_price' => $productData['compare_at_price'],
                        'rating_average' => $productData['rating_average'],
                        'review_count' => $productData['review_count'],
                        'status' => 'active',
                        'is_featured' => $productData['is_featured'],
                        'track_inventory' => true,
                    ]
                );

                foreach ($productData['sizes'] as $size) {
                    $variant = ProductVariant::query()->updateOrCreate(
                        ['sku' => "{$product->style_code}-{$size}"],
                        [
                            'product_id' => $product->id,
                            'name' => "Size {$size}",
                            'barcode' => null,
                            'option_values' => [
                                'size' => $size,
                                'color' => match ($product->slug) {
                                    'ivory-prestige' => 'Ivory/Gold',
                                    'volt-edge' => 'Graphite/Volt',
                                    'azure-velocity' => 'Blue/Black',
                                    default => 'Black/Gold',
                                },
                            ],
                            'price' => $product->base_price,
                            'compare_at_price' => $product->compare_at_price,
                            'weight_grams' => 640,
                            'status' => 'active',
                        ]
                    );

                    $variant->inventoryItem()->updateOrCreate(
                        ['product_variant_id' => $variant->id],
                        [
                            'quantity_on_hand' => 24,
                            'reserved_quantity' => 0,
                            'reorder_level' => 4,
                            'allow_backorder' => false,
                        ]
                    );
                }
            }
        }
    }
}
