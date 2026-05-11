<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductReview;
use App\Models\Orders\OrderItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductReview>
 */
class ProductReviewFactory extends Factory
{
    protected $model = ProductReview::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'user_id' => User::factory(),
            'order_item_id' => null,
            'rating' => fake()->numberBetween(3, 5),
            'title' => fake()->optional()->sentence(4),
            'body' => fake()->paragraph(),
            'is_verified_purchase' => false,
            'is_visible' => true,
        ];
    }

    public function verified(OrderItem $orderItem): static
    {
        return $this->state(fn (): array => [
            'product_id' => $orderItem->product_id,
            'user_id' => $orderItem->order->user_id,
            'order_item_id' => $orderItem->id,
            'is_verified_purchase' => true,
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn (): array => [
            'is_visible' => false,
        ]);
    }
}
