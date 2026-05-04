<?php

use App\Models\Catalog\Product;
use App\Services\Catalog\CatalogQueryService;
use Database\Seeders\Catalog\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the seeded catalog populates the homepage featured pieces showcase', function () {
    $this->seed(CatalogSeeder::class);

    $catalogQuery = app(CatalogQueryService::class);
    $heroProduct = $catalogQuery->heroProduct();
    $featuredProducts = $catalogQuery->showcaseProducts($heroProduct, 4);

    expect(Product::query()->count())->toBeGreaterThan(0)
        ->and(Product::query()->where('status', 'active')->count())->toBeGreaterThanOrEqual(4)
        ->and(Product::query()->where('status', 'active')->where('is_featured', true)->count())->toBeGreaterThan(0)
        ->and($heroProduct)->not->toBeNull()
        ->and($featuredProducts)->toHaveCount(4)
        ->and($featuredProducts->every(fn (Product $product): bool => $product->status === 'active'))->toBeTrue();

    $response = $this->get(route('storefront.home'))
        ->assertOk()
        ->assertDontSeeText('No featured products available yet.');

    expect(substr_count($response->getContent(), 'class="ys-featured-card group"'))->toBe(4);
});
