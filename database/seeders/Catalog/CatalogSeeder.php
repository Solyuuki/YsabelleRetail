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
                        'featured_rank' => 1,
                        'short_description' => 'Featherlight performance runner with carbon-infused energy return.',
                        'description' => 'Featherlight performance runner crafted with breathable engineered mesh and a responsive carbon-infused midsole. Built for distance, designed for legacy.',
                        'primary_image_url' => 'https://images.unsplash.com/photo-1549298916-b41d501d3772?auto=format&fit=crop&w=1400&q=80',
                        'image_alt' => 'Aurum Runner premium sneaker product image',
                        'image_gallery' => [
                            'https://images.unsplash.com/photo-1549298916-b41d501d3772?auto=format&fit=crop&w=1400&q=80',
                            'https://images.unsplash.com/photo-1600185365483-26d7a4cc7519?auto=format&fit=crop&w=1400&q=80',
                        ],
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
                        'featured_rank' => 2,
                        'short_description' => 'Stealth silhouette with cushioned comfort for everyday mileage.',
                        'description' => 'A minimalist runner tuned for daily training with soft underfoot support, tonal detailing, and a low-profile silhouette.',
                        'primary_image_url' => 'https://images.unsplash.com/photo-1525966222134-fcfa99b8ae77?auto=format&fit=crop&w=1400&q=80',
                        'image_alt' => 'Shadow Stride premium sneaker product image',
                        'image_gallery' => [
                            'https://images.unsplash.com/photo-1525966222134-fcfa99b8ae77?auto=format&fit=crop&w=1400&q=80',
                            'https://images.unsplash.com/photo-1514989940723-e8e51635b782?auto=format&fit=crop&w=1400&q=80',
                        ],
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
                        'featured_rank' => null,
                        'short_description' => 'Electric pace trainer with a cool-toned performance finish.',
                        'description' => 'A lively everyday pair with lightweight support, visual contrast, and a race-inspired stance for modern city runs.',
                        'primary_image_url' => 'https://images.unsplash.com/photo-1460353581641-37baddab0fa2?auto=format&fit=crop&w=1400&q=80',
                        'image_alt' => 'Azure Velocity premium sneaker product image',
                        'image_gallery' => [
                            'https://images.unsplash.com/photo-1460353581641-37baddab0fa2?auto=format&fit=crop&w=1400&q=80',
                        ],
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
                        'featured_rank' => 3,
                        'short_description' => 'Clean court-inspired sneaker finished with warm metallic restraint.',
                        'description' => 'An elevated lifestyle silhouette with bright leather, subtle metallic detailing, and a polished low-top stance.',
                        'primary_image_url' => 'https://images.unsplash.com/photo-1600185365483-26d7a4cc7519?auto=format&fit=crop&w=1400&q=80',
                        'image_alt' => 'Ivory Prestige premium sneaker product image',
                        'image_gallery' => [
                            'https://images.unsplash.com/photo-1600185365483-26d7a4cc7519?auto=format&fit=crop&w=1400&q=80',
                            'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=1400&q=80',
                        ],
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
                        'featured_rank' => 4,
                        'short_description' => 'Sport utility pair with visible performance grip and neon edge.',
                        'description' => 'A responsive sport silhouette with high-contrast edge detailing, stable cushioning, and everyday support.',
                        'primary_image_url' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=1400&q=80',
                        'image_alt' => 'Volt Edge premium sneaker product image',
                        'image_gallery' => [
                            'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=1400&q=80',
                        ],
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
                        'primary_image_url' => $productData['primary_image_url'] ?? null,
                        'image_alt' => $productData['image_alt'] ?? null,
                        'image_gallery' => $productData['image_gallery'] ?? null,
                        'base_price' => $productData['base_price'],
                        'compare_at_price' => $productData['compare_at_price'],
                        'rating_average' => $productData['rating_average'],
                        'review_count' => $productData['review_count'],
                        'status' => 'active',
                        'is_featured' => $productData['is_featured'],
                        'featured_rank' => $productData['featured_rank'] ?? null,
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
