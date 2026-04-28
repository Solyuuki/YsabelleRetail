<?php

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('storefront product grid paginates and preserves browse filters', function () {
    $category = Category::factory()->create([
        'name' => 'Running Shoes',
        'slug' => 'running',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    foreach (range(1, 15) as $index) {
        Product::factory()
            ->for($category)
            ->create([
                'name' => sprintf('Paged Runner %02d', $index),
                'slug' => sprintf('paged-runner-%02d', $index),
                'style_code' => sprintf('YS-PGD-%04d', $index),
                'status' => 'active',
                'is_featured' => false,
                'featured_rank' => null,
            ]);
    }

    $this->get(route('storefront.shop', [
        'search' => 'Paged Runner',
        'category' => 'running',
        'sort' => 'name',
    ]))
        ->assertOk()
        ->assertSeeTextInOrder(['Showing 1-12', 'of 15 products'])
        ->assertSee('Paged Runner 01')
        ->assertSee('Paged Runner 12')
        ->assertDontSee('Paged Runner 13')
        ->assertSee('name="category" value="running"', escape: false)
        ->assertSee('category=running', escape: false)
        ->assertSee('sort=name', escape: false)
        ->assertSee('page=2', escape: false);

    $this->get(route('storefront.shop', [
        'search' => 'Paged Runner',
        'category' => 'running',
        'sort' => 'name',
        'page' => 2,
    ]))
        ->assertOk()
        ->assertSeeTextInOrder(['Showing 13-15', 'of 15 products'])
        ->assertSee('Paged Runner 13')
        ->assertSee('Paged Runner 15')
        ->assertDontSee('Paged Runner 01');
});

test('storefront product grid supports compact pagination and hides controls for empty results', function () {
    $category = Category::factory()->create([
        'name' => 'Lifestyle Shoes',
        'slug' => 'lifestyle-shoes',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    foreach (range(1, 9) as $index) {
        Product::factory()
            ->for($category)
            ->create([
                'name' => sprintf('Compact Muse %02d', $index),
                'slug' => sprintf('compact-muse-%02d', $index),
                'style_code' => sprintf('YS-CMP-%04d', $index),
                'status' => 'active',
            ]);
    }

    $this->get(route('storefront.shop', [
        'search' => 'Compact Muse',
        'category' => 'lifestyle-shoes',
        'sort' => 'name',
        'per_page' => 8,
    ]))
        ->assertOk()
        ->assertSeeTextInOrder(['Showing 1-8', 'of 9 products'])
        ->assertSee('Compact Muse 08')
        ->assertDontSee('Compact Muse 09')
        ->assertSee('name="per_page" value="8"', escape: false)
        ->assertSee('per_page=8', escape: false)
        ->assertSee('page=2', escape: false);

    $this->get(route('storefront.shop', [
        'search' => 'zzznomatchtoken',
        'category' => 'lifestyle-shoes',
        'per_page' => 8,
    ]))
        ->assertOk()
        ->assertSeeText('No products found.')
        ->assertDontSee('Pagination Navigation');
});
