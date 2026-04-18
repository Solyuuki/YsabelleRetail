<?php

namespace Database\Seeders\Catalog;

use App\Models\Catalog\Category;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        Category::factory()
            ->count(4)
            ->has(
                \App\Models\Catalog\Product::factory()
                    ->count(3)
                    ->has(
                        \App\Models\Catalog\ProductVariant::factory()
                            ->count(2)
                            ->afterCreating(function (\App\Models\Catalog\ProductVariant $variant): void {
                                $variant->inventoryItem()->create([
                                    'quantity_on_hand' => fake()->numberBetween(5, 30),
                                    'reserved_quantity' => 0,
                                    'reorder_level' => 3,
                                    'allow_backorder' => false,
                                ]);
                            })
                    )
            )
            ->create();
    }
}
