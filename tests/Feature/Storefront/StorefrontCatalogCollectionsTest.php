<?php

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Orders\Order;
use App\Services\Catalog\CatalogQueryService;
use Database\Seeders\Catalog\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('default shop keeps the base catalog path and only shows active products', function () {
    $category = Category::factory()->create([
        'name' => 'Running',
        'slug' => 'running',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $activeProduct = Product::factory()->for($category)->create([
        'name' => 'Base Catalog Runner',
        'slug' => 'base-catalog-runner',
        'style_code' => 'YS-BAS-0001',
        'status' => 'active',
        'is_featured' => true,
    ]);

    Product::factory()->for($category)->create([
        'name' => 'Archived Catalog Runner',
        'slug' => 'archived-catalog-runner',
        'style_code' => 'YS-ARC-0002',
        'status' => 'archived',
    ]);

    $this->get(route('storefront.shop'))
        ->assertOk()
        ->assertSeeText('Shop all shoes')
        ->assertSeeText($activeProduct->name)
        ->assertDontSeeText('Archived Catalog Runner')
        ->assertSee('ys-status-pill bg-ys-gold text-ys-ink">New</span>', escape: false);
});

test('new arrivals collection uses real product timestamps and keeps category filters active', function () {
    $category = Category::factory()->create([
        'name' => 'Running',
        'slug' => 'running',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $oldest = Product::factory()->for($category)->create([
        'name' => 'Legacy Pace',
        'slug' => 'legacy-pace',
        'style_code' => 'YS-LEG-1001',
        'status' => 'active',
        'is_featured' => false,
        'created_at' => Carbon::parse('2026-01-10 08:00:00'),
        'updated_at' => Carbon::parse('2026-01-10 08:00:00'),
    ]);

    $middle = Product::factory()->for($category)->create([
        'name' => 'Summit Sprint',
        'slug' => 'summit-sprint',
        'style_code' => 'YS-SUM-1002',
        'status' => 'active',
        'is_featured' => false,
        'created_at' => Carbon::parse('2026-03-15 08:00:00'),
        'updated_at' => Carbon::parse('2026-03-15 08:00:00'),
    ]);

    $newest = Product::factory()->for($category)->create([
        'name' => 'Fresh Velocity',
        'slug' => 'fresh-velocity',
        'style_code' => 'YS-FRE-1003',
        'status' => 'active',
        'is_featured' => false,
        'created_at' => Carbon::parse('2026-04-28 08:00:00'),
        'updated_at' => Carbon::parse('2026-04-28 08:00:00'),
    ]);

    $response = $this->get(route('storefront.shop', [
        'collection' => 'new-arrivals',
        'category' => 'running',
    ]));

    $response
        ->assertOk()
        ->assertSeeText('New Arrivals')
        ->assertSeeText('Category: Running')
        ->assertSeeInOrder([
            $newest->name,
            $middle->name,
            $oldest->name,
        ])
        ->assertSee('ys-status-pill bg-ys-gold text-ys-ink">New</span>', escape: false)
        ->assertSee('name="collection" value="new-arrivals"', escape: false)
        ->assertSee('value="best_sellers"', escape: false);

    expect(substr_count($response->getContent(), 'ys-status-pill bg-ys-gold text-ys-ink">New</span>'))->toBe(3);
});

test('seeded new arrivals use a deterministic release chronology instead of the last seeded category', function () {
    $this->seed(CatalogSeeder::class);

    $products = app(CatalogQueryService::class)
        ->products(['collection' => 'new-arrivals'], 12)
        ->getCollection();

    expect($products)->toHaveCount(12)
        ->and($products->pluck('created_at')->unique()->count())->toBeGreaterThan(1)
        ->and($products->pluck('category.slug')->unique()->count())->toBeGreaterThan(1)
        ->and($products->pluck('category.slug')->take(8)->unique()->count())->toBeGreaterThan(4)
        ->and($products->pluck('category.slug')->take(8)->contains('boots-high-cut'))->toBeTrue()
        ->and($products->pluck('category.slug')->take(8)->contains('running'))->toBeTrue();
});

test('best sellers collection ranks products by completed paid order units and ignores unpaid demand', function () {
    $category = Category::factory()->create([
        'name' => 'Lifestyle',
        'slug' => 'lifestyle-shoes',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $topSeller = Product::factory()->for($category)->create([
        'name' => 'Crown Street',
        'slug' => 'crown-street',
        'style_code' => 'YS-CRN-2001',
        'status' => 'active',
        'review_count' => 40,
        'rating_average' => 4.7,
    ]);

    $steadySeller = Product::factory()->for($category)->create([
        'name' => 'Harbor Walk',
        'slug' => 'harbor-walk',
        'style_code' => 'YS-HRB-2002',
        'status' => 'active',
        'review_count' => 32,
        'rating_average' => 4.8,
    ]);

    $pendingOnly = Product::factory()->for($category)->create([
        'name' => 'Mirage Slip',
        'slug' => 'mirage-slip',
        'style_code' => 'YS-MIR-2003',
        'status' => 'active',
        'review_count' => 12,
        'rating_average' => 4.3,
    ]);

    $completedOrder = Order::query()->create([
        'order_number' => 'ORD-COMPLETE-1001',
        'status' => 'completed',
        'payment_status' => 'paid',
        'fulfillment_status' => 'fulfilled',
        'currency' => 'PHP',
        'subtotal_amount' => 10000,
        'discount_amount' => 0,
        'shipping_amount' => 0,
        'tax_amount' => 0,
        'grand_total' => 10000,
        'placed_at' => Carbon::parse('2026-04-20 13:00:00'),
    ]);

    $completedOrder->items()->createMany([
        [
            'product_id' => $topSeller->id,
            'product_name' => $topSeller->name,
            'quantity' => 5,
            'unit_price' => 2000,
            'line_total' => 10000,
        ],
        [
            'product_id' => $steadySeller->id,
            'product_name' => $steadySeller->name,
            'quantity' => 2,
            'unit_price' => 2000,
            'line_total' => 4000,
        ],
    ]);

    $pendingOrder = Order::query()->create([
        'order_number' => 'ORD-PENDING-1002',
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'fulfillment_status' => 'unfulfilled',
        'currency' => 'PHP',
        'subtotal_amount' => 9999,
        'discount_amount' => 0,
        'shipping_amount' => 0,
        'tax_amount' => 0,
        'grand_total' => 9999,
        'placed_at' => Carbon::parse('2026-04-21 09:30:00'),
    ]);

    $pendingOrder->items()->create([
        'product_id' => $pendingOnly->id,
        'product_name' => $pendingOnly->name,
        'quantity' => 50,
        'unit_price' => 199.98,
        'line_total' => 9999,
    ]);

    $response = $this->get(route('storefront.shop', [
        'collection' => 'best-sellers',
    ]));

    $response
        ->assertOk()
        ->assertSeeText('Best Sellers')
        ->assertSeeTextInOrder(['Showing 1-3', 'of 3 products'])
        ->assertSeeInOrder([
            $topSeller->name,
            $steadySeller->name,
            $pendingOnly->name,
        ])
        ->assertSee('name="collection" value="best-sellers"', escape: false)
        ->assertSee('value="best_sellers" selected', escape: false);
});

test('best sellers falls back to a deterministic popularity ranking when no completed paid sales exist', function () {
    $category = Category::factory()->create([
        'name' => 'Sneakers',
        'slug' => 'sneakers',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $saleLeader = Product::factory()->for($category)->create([
        'name' => 'Sale Leader',
        'slug' => 'sale-leader',
        'style_code' => 'YS-SAL-3001',
        'status' => 'active',
        'base_price' => 5000,
        'compare_at_price' => 6200,
        'rating_average' => 4.9,
        'review_count' => 220,
        'is_featured' => false,
        'featured_rank' => null,
    ]);

    $reviewLeader = Product::factory()->for($category)->create([
        'name' => 'Review Leader',
        'slug' => 'review-leader',
        'style_code' => 'YS-REV-3002',
        'status' => 'active',
        'base_price' => 5000,
        'compare_at_price' => null,
        'rating_average' => 4.8,
        'review_count' => 180,
        'is_featured' => false,
        'featured_rank' => null,
    ]);

    $baseline = Product::factory()->for($category)->create([
        'name' => 'Baseline Pick',
        'slug' => 'baseline-pick',
        'style_code' => 'YS-BAS-3003',
        'status' => 'active',
        'base_price' => 5000,
        'compare_at_price' => null,
        'rating_average' => 4.3,
        'review_count' => 20,
        'is_featured' => false,
        'featured_rank' => null,
    ]);

    $response = $this->get(route('storefront.shop', [
        'collection' => 'best-sellers',
    ]));

    $response
        ->assertOk()
        ->assertSeeInOrder([
            $saleLeader->name,
            $reviewLeader->name,
            $baseline->name,
        ])
        ->assertDontSee('ys-status-pill bg-ys-gold text-ys-ink">New</span>', escape: false);
});

test('storefront navigation highlights collection links without keeping shop active', function () {
    Category::factory()->create([
        'name' => 'Running',
        'slug' => 'running',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    Product::factory()->create([
        'name' => 'Navigation Runner',
        'slug' => 'navigation-runner',
        'style_code' => 'YS-NAV-3001',
        'status' => 'active',
    ]);

    $response = $this->get(route('storefront.shop', [
        'collection' => 'new-arrivals',
    ]));

    $content = $response->getContent();

    $response
        ->assertOk()
        ->assertSee('href="'.route('storefront.shop', ['collection' => 'new-arrivals']).'"', escape: false);

    expect($content)
        ->toMatch('/href="'.preg_quote(route('storefront.shop', ['collection' => 'new-arrivals']), '/').'"[^>]*aria-current="page"/')
        ->not->toMatch('/href="'.preg_quote(route('storefront.shop'), '/').'"[^>]*aria-current="page"/');
});
