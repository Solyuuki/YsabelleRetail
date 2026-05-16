<?php

use App\Models\Access\Role;
use App\Models\Orders\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function analyticsAdmin(array $attributes = []): User
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

function analyticsOrder(array $overrides = []): Order
{
    return Order::query()->create(array_merge([
        'source' => 'online',
        'order_number' => 'YSB-ORD-'.Str::upper(Str::random(8)),
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
        'customer_name' => 'Order Customer',
        'customer_email' => 'order@example.com',
        'payment_method' => 'card_simulated',
        'metadata' => ['source' => 'online'],
        'exclude_from_analytics' => false,
    ], $overrides));
}

test('order analytics exclusion command marks safe seeded orders and stays idempotent', function () {
    $cashier = analyticsAdmin(['email' => 'cashier@example.com']);

    $reviewSeed = analyticsOrder([
        'source' => 'storefront',
        'order_number' => 'ORD-RVW-099-01-01',
        'customer_email' => 'review-seed@ysabelle.demo',
        'notes' => 'Demo verified-purchase order seeded for storefront review content.',
        'metadata' => ['demo_seed' => true, 'review_seed' => true],
    ]);

    $onlineSeed = analyticsOrder([
        'source' => 'online',
        'order_number' => 'YSB-DEMO-ONLINE',
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'fulfillment_status' => 'unfulfilled',
        'customer_email' => 'demo-online@ysabelle.demo',
        'notes' => 'Seeded online order for demo reporting.',
    ]);

    $walkInSeed = analyticsOrder([
        'source' => 'walk_in',
        'order_number' => 'YSP-DEMO-WALKIN',
        'customer_email' => null,
        'payment_method' => 'cash',
        'handled_by_user_id' => $cashier->id,
        'notes' => 'Walk-in sale from weekend foot traffic.',
        'metadata' => ['walk_in' => true],
    ]);

    $realOrder = analyticsOrder([
        'order_number' => 'YSB-REAL-ORDER',
        'customer_email' => 'buyer@example.com',
        'notes' => 'Real operational order.',
    ]);

    $uncertain = analyticsOrder([
        'source' => 'walk_in',
        'order_number' => 'TMPTEST1234',
        'customer_email' => 'test@example.com',
        'handled_by_user_id' => null,
        'payment_method' => 'cash',
        'metadata' => [],
    ]);

    $this->artisan('orders:mark-review-seed-analytics-excluded')
        ->expectsOutputToContain('Order analytics exclusion scan complete.')
        ->expectsOutputToContain('Scanned')
        ->expectsOutputToContain('Marked')
        ->assertExitCode(0);

    expect($reviewSeed->fresh()->exclude_from_analytics)->toBeTrue()
        ->and($reviewSeed->fresh()->analytics_exclusion_reason)->toBe('review_support_seed')
        ->and($onlineSeed->fresh()->exclude_from_analytics)->toBeTrue()
        ->and($onlineSeed->fresh()->analytics_exclusion_reason)->toBe('demo_seed_online')
        ->and($walkInSeed->fresh()->exclude_from_analytics)->toBeTrue()
        ->and($walkInSeed->fresh()->analytics_exclusion_reason)->toBe('demo_seed_walk_in')
        ->and($realOrder->fresh()->exclude_from_analytics)->toBeFalse()
        ->and($uncertain->fresh()->exclude_from_analytics)->toBeFalse();

    $this->artisan('orders:mark-review-seed-analytics-excluded')
        ->expectsOutputToContain('Already excluded')
        ->assertExitCode(0);

    expect(Order::query()->analyticsExcluded()->count())->toBe(3);
});

test('order detail page keeps excluded orders visible with an analytics badge', function () {
    $admin = analyticsAdmin();
    $order = analyticsOrder([
        'order_number' => 'YSB-EXCLUDED-DETAIL',
        'exclude_from_analytics' => true,
        'analytics_exclusion_reason' => 'review_support_seed',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertSeeText('Excluded from analytics')
        ->assertSeeText('Review Support Seed');
});
