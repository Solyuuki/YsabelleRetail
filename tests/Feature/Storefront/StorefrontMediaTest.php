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

test('the featured showcase renders four cards even when the hero comes from the featured pool', function () {
    $category = Category::factory()->create([
        'name' => 'Running',
        'slug' => 'running',
        'is_active' => true,
    ]);

    Product::factory()->for($category)->create([
        'name' => 'Aurum Runner',
        'slug' => 'aurum-runner',
        'is_featured' => true,
        'featured_rank' => 1,
        'primary_image_url' => 'https://cdn.ysabelle.test/catalog/aurum-runner.jpg',
        'image_alt' => 'Aurum Runner sneaker image',
    ]);

    Product::factory()->for($category)->create([
        'name' => 'Shadow Stride',
        'slug' => 'shadow-stride',
        'is_featured' => true,
        'featured_rank' => 2,
        'primary_image_url' => 'https://cdn.ysabelle.test/catalog/shadow-stride.jpg',
    ]);

    Product::factory()->for($category)->create([
        'name' => 'Ivory Prestige',
        'slug' => 'ivory-prestige',
        'is_featured' => true,
        'featured_rank' => 3,
        'primary_image_url' => 'https://cdn.ysabelle.test/catalog/ivory-prestige.jpg',
    ]);

    Product::factory()->for($category)->create([
        'name' => 'Volt Edge',
        'slug' => 'volt-edge',
        'is_featured' => false,
        'primary_image_url' => 'https://cdn.ysabelle.test/catalog/volt-edge.jpg',
    ]);

    Product::factory()->for($category)->create([
        'name' => 'Onyx Vector',
        'slug' => 'onyx-vector',
        'is_featured' => false,
        'primary_image_url' => 'https://cdn.ysabelle.test/catalog/onyx-vector.jpg',
    ]);

    $response = $this->get(route('storefront.home'))
        ->assertOk()
        ->assertSee('https://cdn.ysabelle.test/catalog/shadow-stride.jpg', escape: false)
        ->assertSee('https://cdn.ysabelle.test/catalog/ivory-prestige.jpg', escape: false)
        ->assertSee('https://cdn.ysabelle.test/catalog/volt-edge.jpg', escape: false)
        ->assertSee('https://cdn.ysabelle.test/catalog/onyx-vector.jpg', escape: false);

    expect(substr_count($response->getContent(), 'class="ys-featured-card group"'))->toBe(4);
});

test('the featured showcase falls back to the hero product when it is the only active product', function () {
    $category = Category::factory()->create([
        'name' => 'Running',
        'slug' => 'running',
        'is_active' => true,
    ]);

    Product::factory()->for($category)->create([
        'name' => 'Aurum Runner',
        'slug' => 'aurum-runner',
        'is_featured' => true,
        'featured_rank' => 1,
        'primary_image_url' => 'https://cdn.ysabelle.test/catalog/aurum-runner.jpg',
        'image_alt' => 'Aurum Runner sneaker image',
    ]);

    $response = $this->get(route('storefront.home'))
        ->assertOk()
        ->assertSee('https://cdn.ysabelle.test/catalog/aurum-runner.jpg', escape: false)
        ->assertSeeText('Aurum Runner');

    expect(substr_count($response->getContent(), 'class="ys-featured-card group"'))->toBe(1);
});

test('the featured showcase renders a premium empty state when there are no products', function () {
    $this->get(route('storefront.home'))
        ->assertOk()
        ->assertSeeText('No featured products available yet.')
        ->assertSeeText('Browse the catalog')
        ->assertDontSee('class="ys-featured-card group"', escape: false);
});
