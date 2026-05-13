<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'category_id' => Category::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name.'-'.fake()->unique()->numberBetween(1, 9999)),
            'style_code' => 'YS-'.fake()->unique()->numerify('####'),
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraphs(2, true),
            'primary_image_url' => 'https://cdn.ysabelle.test/catalog/'.fake()->uuid().'.jpg',
            'image_alt' => Str::title($name).' product image',
            'image_gallery' => [
                'https://cdn.ysabelle.test/catalog/'.fake()->uuid().'.jpg',
            ],
            'base_price' => fake()->randomFloat(2, 999, 5999),
            'compare_at_price' => fake()->optional()->randomFloat(2, 1999, 6999),
            'rating_average' => 0,
            'review_count' => 0,
            'status' => 'active',
            'is_featured' => fake()->boolean(30),
            'force_new_badge' => false,
            'featured_rank' => fake()->optional()->numberBetween(1, 20),
            'track_inventory' => true,
        ];
    }
}
