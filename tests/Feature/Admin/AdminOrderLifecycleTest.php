<?php

use App\Models\Access\Role;
use App\Models\Orders\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeLifecycleAdmin(): User
{
    $role = Role::query()->firstOrCreate(
        ['slug' => 'admin'],
        [
            'name' => 'Admin',
            'description' => 'Admin role',
            'is_system' => true,
        ],
    );

    $user = User::factory()->create();
    $user->roles()->attach($role);

    return $user;
}

function makeLifecycleCustomer(): User
{
    $role = Role::query()->firstOrCreate(
        ['slug' => 'customer'],
        [
            'name' => 'Customer',
            'description' => 'Customer role',
            'is_system' => true,
        ],
    );

    $user = User::factory()->create();
    $user->roles()->attach($role);

    return $user;
}

function makeOnlineOrder(array $overrides = []): Order
{
    $customer = makeLifecycleCustomer();

    return Order::query()->create(array_merge([
        'user_id' => $customer->id,
        'source' => 'online',
        'handled_by_user_id' => null,
        'order_number' => 'YSR-LIFE-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
        'status' => 'processing',
        'payment_status' => 'paid',
        'fulfillment_status' => 'unfulfilled',
        'currency' => 'PHP',
        'subtotal_amount' => 3200,
        'discount_amount' => 0,
        'shipping_amount' => 350,
        'tax_amount' => 0,
        'grand_total' => 3550,
        'placed_at' => now(),
        'customer_name' => 'Lifecycle Customer',
        'customer_email' => 'lifecycle@example.com',
        'customer_phone' => '09171234567',
        'shipping_city' => 'Makati',
        'shipping_address_line' => '123 Lifecycle Street',
        'shipping_postal_code' => '1200',
        'payment_method' => 'card_simulated',
        'metadata' => ['source' => 'online'],
    ], $overrides));
}

test('admin order detail shows lifecycle controls for open orders', function () {
    $admin = makeLifecycleAdmin();
    $order = makeOnlineOrder();

    $this->actingAs($admin)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertSee('Order Lifecycle')
        ->assertSee('Update lifecycle');
});

test('admin can complete a paid processing online order', function () {
    $admin = makeLifecycleAdmin();
    $order = makeOnlineOrder([
        'status' => 'processing',
        'payment_status' => 'paid',
        'fulfillment_status' => 'unfulfilled',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.orders.lifecycle.update', $order), [
            'status' => 'completed',
            'payment_status' => 'paid',
        ])
        ->assertRedirect(route('admin.orders.show', $order));

    $order->refresh();

    expect($order->status)->toBe('completed')
        ->and($order->payment_status)->toBe('paid')
        ->and($order->fulfillment_status)->toBe('fulfilled');
});

test('admin cannot complete an unpaid order without marking it as paid', function () {
    $admin = makeLifecycleAdmin();
    $order = makeOnlineOrder([
        'payment_method' => 'cod',
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'fulfillment_status' => 'unfulfilled',
    ]);

    $this->from(route('admin.orders.show', $order))
        ->actingAs($admin)
        ->patch(route('admin.orders.lifecycle.update', $order), [
            'status' => 'completed',
            'payment_status' => 'unpaid',
        ])
        ->assertRedirect(route('admin.orders.show', $order))
        ->assertSessionHasErrors(['payment_status']);

    $order->refresh();

    expect($order->status)->toBe('pending')
        ->and($order->payment_status)->toBe('unpaid')
        ->and($order->fulfillment_status)->toBe('unfulfilled');
});

test('admin can mark a pending online order as paid and completed in one update', function () {
    $admin = makeLifecycleAdmin();
    $order = makeOnlineOrder([
        'payment_method' => 'cod',
        'status' => 'pending',
        'payment_status' => 'pending',
        'fulfillment_status' => 'unfulfilled',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.orders.lifecycle.update', $order), [
            'status' => 'completed',
            'payment_status' => 'paid',
        ])
        ->assertRedirect(route('admin.orders.show', $order));

    $order->refresh();

    expect($order->status)->toBe('completed')
        ->and($order->payment_status)->toBe('paid')
        ->and($order->fulfillment_status)->toBe('fulfilled');
});
