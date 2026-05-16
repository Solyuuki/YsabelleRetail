<?php

use App\Models\Access\Role;
use App\Models\Orders\Order;
use App\Models\User;
use App\Services\Admin\AdminDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function dashboardAdmin(array $attributes = []): User
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

function dashboardOrder(array $overrides = []): Order
{
    return Order::query()->create(array_merge([
        'source' => 'online',
        'order_number' => 'YSB-DASH-'.Str::upper(Str::random(8)),
        'status' => 'completed',
        'payment_status' => 'paid',
        'fulfillment_status' => 'fulfilled',
        'currency' => 'PHP',
        'subtotal_amount' => 2500,
        'discount_amount' => 0,
        'shipping_amount' => 0,
        'tax_amount' => 0,
        'grand_total' => 2500,
        'placed_at' => now(),
        'customer_name' => 'Dashboard Customer',
        'customer_email' => 'dashboard@example.com',
        'payment_method' => 'card_simulated',
        'metadata' => ['source' => 'online'],
        'exclude_from_analytics' => false,
    ], $overrides));
}

test('dashboard metrics exclude analytics flagged orders from revenue and order counters', function () {
    dashboardOrder([
        'source' => 'online',
        'grand_total' => 5000,
        'subtotal_amount' => 5000,
    ]);

    dashboardOrder([
        'source' => 'walk_in',
        'grand_total' => 2500,
        'subtotal_amount' => 2500,
        'customer_email' => null,
        'metadata' => ['walk_in' => true],
    ]);

    dashboardOrder([
        'source' => 'storefront',
        'order_number' => 'ORD-RVW-001-01-01',
        'grand_total' => 12000,
        'subtotal_amount' => 12000,
        'exclude_from_analytics' => true,
        'analytics_exclusion_reason' => 'review_support_seed',
        'metadata' => ['demo_seed' => true, 'review_seed' => true],
    ]);

    dashboardOrder([
        'source' => 'online',
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'fulfillment_status' => 'unfulfilled',
        'grand_total' => 4200,
        'subtotal_amount' => 4200,
        'exclude_from_analytics' => true,
        'analytics_exclusion_reason' => 'demo_seed_online',
    ]);

    $metrics = app(AdminDashboardService::class)->summary()['metrics'];

    expect($metrics['total_sales'])->toBe(7500.0)
        ->and($metrics['online_sales'])->toBe(5000.0)
        ->and($metrics['walk_in_sales'])->toBe(2500.0)
        ->and($metrics['total_sales'])->toBe($metrics['online_sales'] + $metrics['walk_in_sales'])
        ->and($metrics['total_orders'])->toBe(2)
        ->and($metrics['completed_orders'])->toBe(2)
        ->and($metrics['pending_orders'])->toBe(0);
});

test('dashboard sales trend uses the analytics included revenue scope', function () {
    $included = dashboardOrder([
        'source' => 'online',
        'grand_total' => 3600,
        'subtotal_amount' => 3600,
        'placed_at' => now()->subDay(),
    ]);

    dashboardOrder([
        'source' => 'storefront',
        'order_number' => 'ORD-RVW-777-01-01',
        'grand_total' => 9900,
        'subtotal_amount' => 9900,
        'placed_at' => $included->placed_at,
        'exclude_from_analytics' => true,
        'analytics_exclusion_reason' => 'review_support_seed',
        'metadata' => ['demo_seed' => true, 'review_seed' => true],
    ]);

    $businessDate = \App\Support\BusinessTime::toBusiness($included->placed_at)->toDateString();
    $chartRow = app(AdminDashboardService::class)->summary()['sales_chart']->keyBy('date')->get($businessDate);

    expect($chartRow['total'])->toBe(3600.0)
        ->and($chartRow['online_total'])->toBe(3600.0)
        ->and($chartRow['walk_in_total'])->toBe(0.0)
        ->and($chartRow['orders_count'])->toBe(1);
});
