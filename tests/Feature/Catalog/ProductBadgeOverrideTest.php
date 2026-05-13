<?php

use App\Models\Catalog\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('manual new badge override keeps the new badge visible for older products', function () {
    $olderProduct = Product::factory()->create([
        'created_at' => now()->subDays(120),
        'updated_at' => now()->subDays(120),
        'force_new_badge' => false,
    ]);

    $forcedProduct = Product::factory()->create([
        'created_at' => now()->subDays(120),
        'updated_at' => now()->subDays(120),
        'force_new_badge' => true,
    ]);

    expect($olderProduct->fresh()->shows_new_badge)->toBeFalse()
        ->and($forcedProduct->fresh()->shows_new_badge)->toBeTrue();
});
