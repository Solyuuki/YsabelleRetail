<?php

use App\Models\Catalog\Product;
use App\Services\Catalog\CatalogQueryService;
use Database\Seeders\Catalog\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('seeded storefront pages only render real product photo assets', function () {
    $this->seed(CatalogSeeder::class);

    $catalogQuery = app(CatalogQueryService::class);
    $featuredProduct = $catalogQuery->showcaseProducts($catalogQuery->heroProduct(), 4)->first();
    $detailProduct = Product::query()->orderBy('id')->firstOrFail();

    expect(Product::query()->get()->every(function (Product $product): bool {
        return is_string($product->primary_image_url)
            && preg_match('/\.(jpg|jpeg|webp)$/i', $product->primary_image_url) === 1
            && collect($product->image_gallery ?? [])->every(
                fn (string $url): bool => preg_match('/\.(jpg|jpeg|webp)$/i', $url) === 1
            );
    }))->toBeTrue();

    $home = $this->get(route('storefront.home'))->assertOk();
    $shop = $this->get(route('storefront.shop'))->assertOk();
    $detail = $this->get(route('storefront.catalog.products.show', $detailProduct))->assertOk();

    expect($home->getContent())->toContain((string) $featuredProduct?->primary_image_url)
        ->not->toMatch('/images\/products\/[^"\']+\.png/i');

    expect($shop->getContent())->not->toMatch('/images\/products\/[^"\']+\.png/i');

    expect($detail->getContent())->toContain((string) $detailProduct->primary_image_url)
        ->not->toMatch('/images\/products\/[^"\']+\.png/i');
});
