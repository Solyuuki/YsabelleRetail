<?php

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the storefront hero renders the isolated Polycam shoe capture and matching hero copy', function () {
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
        ->assertSee('https://poly.cam/explore/capture/3051A780-6C9D-45B7-A1A1-D568C3839F63/Nike+Shoe+V2', escape: false)
        ->assertSee(asset('models/storefront/polycam-nike-shoe-v2/poly.gltf'), escape: false)
        ->assertSee(asset('images/storefront/hdri/small_hangar_01_1k.hdr'), escape: false)
        ->assertSee('https://cdn.ysabelle.test/catalog/secondary.jpg', escape: false)
        ->assertSee('data-hero-showcase', escape: false)
        ->assertSee('<model-viewer', escape: false)
        ->assertSeeText('Polycam Capture / Charcoal / White / Orange Accent')
        ->assertSeeText('Nike Shoe V2 shows a charcoal mesh runner with black laces and collar, a crisp white Swoosh, a sculpted white sole, and a small orange eyelet accent taken directly from the Polycam source capture.')
        ->assertSeeText('Nike Shoe V2')
        ->assertSeeText('55,389')
        ->assertSeeText('Published');
});
