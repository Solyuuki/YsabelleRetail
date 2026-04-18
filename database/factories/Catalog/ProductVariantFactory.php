<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        $size = fake()->randomElement(['36', '37', '38', '39', '40']);
        $color = fake()->randomElement(['Black', 'White', 'Nude']);
        $sku = 'YSV-'.fake()->unique()->numerify('######');

        return [
            'product_id' => Product::factory(),
            'name' => "Size {$size} / {$color} / {$sku}",
            'sku' => $sku,
            'barcode' => fake()->optional()->ean13(),
            'option_values' => [
                'size' => $size,
                'color' => $color,
            ],
            'price' => fake()->randomFloat(2, 999, 5999),
            'compare_at_price' => fake()->optional()->randomFloat(2, 1999, 6999),
            'weight_grams' => fake()->numberBetween(300, 1200),
            'status' => 'active',
        ];
    }
}
