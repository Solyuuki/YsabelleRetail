<?php

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the storefront hero links to a featured product record and renders its media url', function () {
    $category = Category::factory()->create([
        'name' => 'Running',
        'slug' => 'running',
        'is_active' => true,
    ]);

    $heroProduct = Product::factory()->for($category)->create([
        'name' => 'Aurum Runner',
        'slug' => 'aurum-runner',
        'is_featured' => true,
        'featured_rank' => 1,
        'primary_image_url' => 'https://cdn.ysabelle.test/catalog/aurum-runner.jpg',
        'image_alt' => 'Aurum Runner sneaker image',
    ]);

    Product::factory()->for($category)->create([
        'is_featured' => true,
        'featured_rank' => 2,
        'primary_image_url' => 'https://cdn.ysabelle.test/catalog/secondary.jpg',
    ]);

    $this->get(route('storefront.home'))
        ->assertOk()
        ->assertSee(route('storefront.catalog.products.show', $heroProduct), escape: false)
        ->assertSee('https://cdn.ysabelle.test/catalog/aurum-runner.jpg', escape: false)
        ->assertSeeText('Aurum Runner');
});
