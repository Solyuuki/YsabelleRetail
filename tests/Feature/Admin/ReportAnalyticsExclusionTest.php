<?php

use App\Models\Access\Role;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Models\Orders\Order;
use App\Services\Reports\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function reportAdmin(array $attributes = []): User
{
    $role = Role::query()->firstOrCreate(
        ['slug' => 'admin'],
        [
            'name' => 'Admin',
            'description' => 'Admin role',
            'is_system' => true,
        ],
    );

    $user = User::factory()->create($attributes);
    $user->roles()->syncWithoutDetaching([$role->id]);

    return $user;
}

function reportVariant(): ProductVariant
{
    $category = Category::factory()->create([
        'name' => 'Report Category',
        'slug' => 'report-category',
    ]);

    $product = Product::factory()->for($category)->create([
        'name' => 'Report Runner',
        'slug' => 'report-runner',
        'status' => 'active',
    ]);

    $variant = ProductVariant::factory()->for($product)->create([
        'name' => 'Size 9',
        'status' => 'active',
        'price' => 3200,
    ]);

    $variant->inventoryItem()->create([
        'quantity_on_hand' => 12,
        'reserved_quantity' => 0,
        'reorder_level' => 2,
        'allow_backorder' => false,
    ]);

    return $variant->fresh(['product.category']);
}

function reportOrder(ProductVariant $variant, array $overrides = [], int $quantity = 1, ?float $unitPrice = null): Order
{
    $price = $unitPrice ?? (float) $variant->price;
    $total = $price * $quantity;

    $order = Order::query()->create(array_merge([
        'source' => 'online',
        'order_number' => 'YSB-RPT-'.Str::upper(Str::random(8)),
        'status' => 'completed',
        'payment_status' => 'paid',
        'fulfillment_status' => 'fulfilled',
        'currency' => 'PHP',
        'subtotal_amount' => $total,
        'discount_amount' => 0,
        'shipping_amount' => 0,
        'tax_amount' => 0,
        'grand_total' => $total,
        'placed_at' => now(),
        'customer_name' => 'Report Customer',
        'customer_email' => 'report@example.com',
        'payment_method' => 'card_simulated',
        'metadata' => ['source' => 'online'],
        'exclude_from_analytics' => false,
    ], $overrides));

    $order->items()->create([
        'product_id' => $variant->product_id,
        'product_variant_id' => $variant->id,
        'product_name' => $variant->product->name,
        'variant_name' => $variant->name,
        'sku' => $variant->sku,
        'quantity' => $quantity,
        'unit_price' => $price,
        'line_total' => $total,
        'metadata' => ['source' => $order->source],
    ]);

    return $order->fresh('items');
}

test('report revenue datasets exclude analytics flagged orders and keep normal sales included', function () {
    $variant = reportVariant();

    $includedOnline = reportOrder($variant, [
        'source' => 'online',
        'customer_email' => 'buyer@example.com',
    ]);

    $includedWalkIn = reportOrder($variant, [
        'source' => 'walk_in',
        'order_number' => 'YSP-RPT-0001',
        'customer_email' => null,
        'payment_method' => 'cash',
        'metadata' => ['walk_in' => true],
    ], quantity: 2, unitPrice: 1200);

    reportOrder($variant, [
        'source' => 'storefront',
        'order_number' => 'ORD-RVW-444-01-01',
        'customer_email' => 'seeded@ysabelle.demo',
        'grand_total' => 9000,
        'subtotal_amount' => 9000,
        'exclude_from_analytics' => true,
        'analytics_exclusion_reason' => 'review_support_seed',
        'metadata' => ['demo_seed' => true, 'review_seed' => true],
    ], quantity: 1, unitPrice: 9000);

    $reportService = app(ReportService::class);

    $sales = $reportService->build('sales', ['report' => 'sales', 'date_from' => null, 'date_to' => null, 'category_id' => null, 'stock_status' => 'all'], null);
    $walkIn = $reportService->build('walk_in_sales', ['report' => 'walk_in_sales', 'date_from' => null, 'date_to' => null, 'category_id' => null, 'stock_status' => 'all'], null);
    $performance = $reportService->build('product_performance', ['report' => 'product_performance', 'date_from' => null, 'date_to' => null, 'category_id' => null, 'stock_status' => 'all'], null);

    expect($sales['totals']['orders'])->toBe(1)
        ->and($sales['totals']['sales'])->toBe((float) $includedOnline->grand_total)
        ->and(collect($sales['rows'])->pluck(0)->all())->toBe([$includedOnline->order_number]);

    expect($walkIn['totals']['sales'])->toBe(1)
        ->and($walkIn['totals']['amount'])->toBe((float) $includedWalkIn->grand_total)
        ->and(collect($walkIn['rows'])->pluck(0)->all())->toBe([$includedWalkIn->order_number]);

    expect($performance['totals']['units_sold'])->toBe(3)
        ->and($performance['totals']['revenue'])->toBe((float) $includedOnline->grand_total + (float) $includedWalkIn->grand_total)
        ->and($performance['rows']->first()[0])->toBe('Report Runner');
});

test('report page hides analytics excluded sales rows from admins', function () {
    $admin = reportAdmin();
    $variant = reportVariant();

    $included = reportOrder($variant, [
        'order_number' => 'YSB-RPT-INCLUDED',
        'customer_email' => 'visible@example.com',
    ]);

    reportOrder($variant, [
        'source' => 'storefront',
        'order_number' => 'ORD-RVW-HIDDEN',
        'customer_email' => 'hidden@ysabelle.demo',
        'grand_total' => 8100,
        'subtotal_amount' => 8100,
        'exclude_from_analytics' => true,
        'analytics_exclusion_reason' => 'review_support_seed',
        'metadata' => ['demo_seed' => true, 'review_seed' => true],
    ], quantity: 1, unitPrice: 8100);

    $this->actingAs($admin)
        ->get(route('admin.reports.index', ['report' => 'sales']))
        ->assertOk()
        ->assertSeeText($included->order_number)
        ->assertDontSeeText('ORD-RVW-HIDDEN');
});
