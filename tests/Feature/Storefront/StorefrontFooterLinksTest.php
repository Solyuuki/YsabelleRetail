<?php

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('footer renders real storefront links for shop and support navigation', function () {
    $response = $this->get(route('storefront.home'));

    $response
        ->assertOk()
        ->assertSee('href="'.route('storefront.shop').'"', escape: false)
        ->assertSee('href="'.route('storefront.shop', ['category' => 'running']).'"', escape: false)
        ->assertSee('href="'.route('storefront.shop', ['category' => 'sneakers']).'"', escape: false)
        ->assertSee('href="'.route('storefront.shop', ['use_case' => 'performance']).'"', escape: false)
        ->assertSee('href="'.route('storefront.support.size-guide').'"', escape: false)
        ->assertSee('href="'.route('storefront.support.shipping').'"', escape: false)
        ->assertSee('href="'.route('storefront.support.returns').'"', escape: false)
        ->assertSee('href="'.route('storefront.support.contact').'"', escape: false);
});

test('footer shop links resolve to real catalog filters', function () {
    $running = Category::factory()->create([
        'name' => 'Running Shoes',
        'slug' => 'running',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $sneakers = Category::factory()->create([
        'name' => 'Sneakers',
        'slug' => 'sneakers',
        'is_active' => true,
        'sort_order' => 2,
    ]);

    $basketball = Category::factory()->create([
        'name' => 'Basketball Shoes',
        'slug' => 'basketball-shoes',
        'is_active' => true,
        'sort_order' => 3,
    ]);

    $training = Category::factory()->create([
        'name' => 'Training Shoes',
        'slug' => 'training-shoes',
        'is_active' => true,
        'sort_order' => 4,
    ]);

    Product::factory()->for($running)->create([
        'name' => 'Marathon Crest',
        'slug' => 'marathon-crest',
        'style_code' => 'YS-RUN-5001',
        'status' => 'active',
    ]);

    Product::factory()->for($sneakers)->create([
        'name' => 'Street Court',
        'slug' => 'street-court',
        'style_code' => 'YS-SNK-5002',
        'status' => 'active',
    ]);

    Product::factory()->for($basketball)->create([
        'name' => 'Arena Pulse',
        'slug' => 'arena-pulse',
        'style_code' => 'YS-BSK-5003',
        'status' => 'active',
    ]);

    Product::factory()->for($training)->create([
        'name' => 'Circuit Form',
        'slug' => 'circuit-form',
        'style_code' => 'YS-TRN-5004',
        'status' => 'active',
    ]);

    $this->get(route('storefront.shop'))
        ->assertOk()
        ->assertSeeText('Marathon Crest')
        ->assertSeeText('Street Court');

    $this->get(route('storefront.shop', ['category' => 'running']))
        ->assertOk()
        ->assertSeeText('Marathon Crest')
        ->assertDontSeeText('Street Court');

    $this->get(route('storefront.shop', ['category' => 'sneakers']))
        ->assertOk()
        ->assertSeeText('Street Court')
        ->assertDontSeeText('Marathon Crest');

    $this->get(route('storefront.shop', ['use_case' => 'performance']))
        ->assertOk()
        ->assertSeeText('Use case: Performance')
        ->assertSeeText('Marathon Crest')
        ->assertSeeText('Arena Pulse')
        ->assertSeeText('Circuit Form')
        ->assertDontSeeText('Street Court');
});

test('footer support pages resolve with premium storefront content', function () {
    $this->get(route('storefront.support.size-guide'))
        ->assertOk()
        ->assertSeeText('Size Guide')
        ->assertSeeText('US shoe sizes');

    $this->get(route('storefront.support.shipping'))
        ->assertOk()
        ->assertSeeText('Shipping')
        ->assertSeeText('PHP 5,000');

    $this->get(route('storefront.support.returns'))
        ->assertOk()
        ->assertSeeText('Returns')
        ->assertSeeText('14 days');

    $this->get(route('storefront.support.contact'))
        ->assertOk()
        ->assertSeeText('Contact')
        ->assertSeeText('does not currently process a live contact form submission')
        ->assertSee('mailto:care@ysabelle-retail.example', escape: false);
});
