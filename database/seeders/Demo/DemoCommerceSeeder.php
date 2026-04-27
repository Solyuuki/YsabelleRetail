<?php

namespace Database\Seeders\Demo;

use App\Models\Access\Role;
use App\Models\Audit\AuditLog;
use App\Models\Cart\Cart;
use App\Models\Catalog\ProductVariant;
use App\Models\Inventory\InventoryImportBatch;
use App\Models\Inventory\StockMovement;
use App\Models\Orders\Order;
use App\Models\User;
use App\Services\Admin\WalkInSaleService;
use App\Services\Inventory\InventoryManager;
use App\Services\Storefront\CheckoutService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoCommerceSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local') || Order::query()->exists()) {
            return;
        }

        $admin = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('slug', ['admin', 'super-admin']))
            ->first();

        if (! $admin) {
            return;
        }

        $customerRole = Role::query()->where('slug', 'customer')->first();

        if (! $customerRole) {
            return;
        }

        $customers = collect([
            [
                'name' => 'Marianne Cruz',
                'email' => 'marianne.cruz@ysabelle.demo',
                'mobile' => '09171234567',
            ],
            [
                'name' => 'Paolo Mendoza',
                'email' => 'paolo.mendoza@ysabelle.demo',
                'mobile' => '09182345678',
            ],
            [
                'name' => 'Rica Torres',
                'email' => 'rica.torres@ysabelle.demo',
                'mobile' => '09193456789',
            ],
        ])->map(function (array $profile) use ($customerRole): User {
            $user = User::query()->firstOrCreate(
                ['email' => $profile['email']],
                [
                    'name' => $profile['name'],
                    'password' => 'Password123x',
                    'status' => 'active',
                ],
            );

            $user->roles()->syncWithoutDetaching([$customerRole->id]);
            $user->profile()->updateOrCreate([], [
                'preferred_name' => $profile['name'],
                'mobile_number' => $profile['mobile'],
            ]);

            return $user;
        });

        $variants = ProductVariant::query()
            ->with(['product', 'inventoryItem'])
            ->where('status', 'active')
            ->orderBy('sku')
            ->get()
            ->keyBy('sku');

        if ($variants->count() < 6) {
            return;
        }

        $inventory = app(InventoryManager::class);
        $checkout = app(CheckoutService::class);
        $walkIn = app(WalkInSaleService::class);

        $batch = InventoryImportBatch::query()->create([
            'reference_number' => 'IMP-DEMO-240401',
            'uploaded_by_user_id' => $admin->id,
            'original_filename' => 'ysabelle-demo-restock.xlsx',
            'status' => 'completed',
            'total_rows' => 3,
            'imported_rows' => 3,
            'failed_rows' => 0,
            'metadata' => ['demo_seed' => true],
        ]);

        foreach ([
            ['sku' => 'YS-AUR-7490-9', 'qty' => 12, 'cost' => 3550.00, 'supplier' => 'North Metro Footwear Hub'],
            ['sku' => 'YS-SHD-6490-8', 'qty' => 8, 'cost' => 2950.00, 'supplier' => 'North Metro Footwear Hub'],
            ['sku' => 'YS-IVR-5890-7', 'qty' => 6, 'cost' => 3100.00, 'supplier' => 'Central Luxe Traders'],
        ] as $line) {
            $movement = $inventory->importStock(
                variant: $variants->get($line['sku']),
                quantity: $line['qty'],
                batch: $batch,
                actor: $admin,
                notes: 'Demo replenishment batch for reporting and stock history.',
                unitCost: $line['cost'],
                supplierName: $line['supplier'],
                metadata: ['demo_seed' => true],
            );

            $this->retimeMovement($movement, Carbon::now()->subDays(18)->setTime(9, 30));
        }

        $batch->forceFill([
            'created_at' => Carbon::now()->subDays(18)->setTime(9, 15),
            'updated_at' => Carbon::now()->subDays(18)->setTime(9, 35),
        ])->save();

        $onlineOrders = [
            [
                'customer' => $customers[0],
                'placed_at' => Carbon::now()->subDays(7)->setTime(10, 15),
                'payment_method' => 'card_simulated',
                'items' => [
                    ['sku' => 'YS-AUR-7490-9', 'quantity' => 1],
                    ['sku' => 'YS-IVR-5890-7', 'quantity' => 1],
                ],
                'shipping' => [
                    'city' => 'Quezon City',
                    'address' => '42 Scout Torillo Street',
                    'postal_code' => '1103',
                ],
            ],
            [
                'customer' => $customers[1],
                'placed_at' => Carbon::now()->subDays(5)->setTime(14, 40),
                'payment_method' => 'cod',
                'items' => [
                    ['sku' => 'YS-SHD-6490-8', 'quantity' => 2],
                ],
                'shipping' => [
                    'city' => 'Pasig City',
                    'address' => '8 Emerald Avenue',
                    'postal_code' => '1605',
                ],
            ],
            [
                'customer' => $customers[2],
                'placed_at' => Carbon::now()->subDays(2)->setTime(19, 10),
                'payment_method' => 'card_simulated',
                'items' => [
                    ['sku' => 'YS-VLT-5790-10', 'quantity' => 1],
                    ['sku' => 'YS-ONX-6290-9', 'quantity' => 1],
                ],
                'shipping' => [
                    'city' => 'Makati City',
                    'address' => '19 Salcedo Street',
                    'postal_code' => '1227',
                ],
            ],
        ];

        foreach ($onlineOrders as $entry) {
            $cart = Cart::query()->create([
                'user_id' => $entry['customer']->id,
                'status' => 'active',
                'currency' => 'PHP',
            ]);

            foreach ($entry['items'] as $item) {
                $variant = $variants->get($item['sku']);

                $cart->items()->create([
                    'product_variant_id' => $variant->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $variant->price,
                    'line_total' => $item['quantity'] * (float) $variant->price,
                    'metadata' => ['demo_seed' => true],
                ]);
            }

            $order = $checkout->placeOrder($cart->fresh(['items.variant.product']), $entry['customer'], [
                'full_name' => $entry['customer']->name,
                'email' => $entry['customer']->email,
                'phone' => $entry['customer']->profile?->mobile_number,
                'city' => $entry['shipping']['city'],
                'address' => $entry['shipping']['address'],
                'postal_code' => $entry['shipping']['postal_code'],
                'payment_method' => $entry['payment_method'],
                'order_notes' => 'Seeded online order for demo reporting.',
                'cardholder_name' => $entry['payment_method'] === 'card_simulated' ? $entry['customer']->name : null,
                'card_number' => $entry['payment_method'] === 'card_simulated' ? '4242424242424242' : null,
                'card_expiry' => $entry['payment_method'] === 'card_simulated' ? '12/30' : null,
                'card_cvc' => $entry['payment_method'] === 'card_simulated' ? '123' : null,
            ]);

            $this->retimeOrder($order, $entry['placed_at']);
            $cart->delete();
        }

        foreach ([
            [
                'placed_at' => Carbon::now()->subDays(4)->setTime(11, 20),
                'payment_method' => 'cash',
                'payment_status' => 'paid',
                'customer_name' => '',
                'notes' => 'Walk-in sale from weekend foot traffic.',
                'lines' => [
                    ['variant_id' => $variants->get('YS-AZV-5790-8')->id, 'quantity' => 1],
                    ['variant_id' => $variants->get('YS-SHD-6490-8')->id, 'quantity' => 1],
                ],
            ],
            [
                'placed_at' => Carbon::now()->subDay()->setTime(16, 45),
                'payment_method' => 'gcash',
                'payment_status' => 'paid',
                'customer_name' => 'Nina Santos',
                'notes' => 'Reserved item picked up in store.',
                'lines' => [
                    ['variant_id' => $variants->get('YS-ONX-6290-9')->id, 'quantity' => 1],
                ],
            ],
        ] as $entry) {
            $order = $walkIn->create([
                'payment_method' => $entry['payment_method'],
                'payment_status' => $entry['payment_status'],
                'customer_name' => $entry['customer_name'],
                'notes' => $entry['notes'],
                'lines' => $entry['lines'],
            ], $admin);

            $this->retimeOrder($order, $entry['placed_at']);
        }

        foreach ([
            ['sku' => 'YS-SHD-6490-8', 'target' => 2, 'time' => Carbon::now()->subHours(8)],
            ['sku' => 'YS-IVR-5890-7', 'target' => 0, 'time' => Carbon::now()->subHours(5)],
            ['sku' => 'YS-VLT-5790-10', 'target' => 3, 'time' => Carbon::now()->subHours(2)],
        ] as $adjustment) {
            $variant = $variants->get($adjustment['sku'])->fresh(['product', 'inventoryItem']);
            $current = (int) ($variant->inventoryItem?->quantity_on_hand ?? 0);

            $movement = $inventory->recordManualChange(
                variant: $variant,
                quantity: $adjustment['target'] - $current,
                type: 'adjustment',
                actor: $admin,
                referenceNumber: 'AUDIT-DEMO',
                notes: 'Cycle count adjustment seeded for demo alerts and reports.',
                metadata: ['demo_seed' => true],
            );

            $this->retimeMovement($movement, $adjustment['time']);
        }
    }

    private function retimeOrder(Order $order, Carbon $placedAt): void
    {
        $order->forceFill([
            'placed_at' => $placedAt,
            'created_at' => $placedAt,
            'updated_at' => $placedAt,
        ])->save();

        $order->payments()->get()->each(function ($payment) use ($placedAt): void {
            $payment->forceFill([
                'paid_at' => $payment->paid_at ? $placedAt : null,
                'created_at' => $placedAt,
                'updated_at' => $placedAt,
            ])->save();
        });

        $order->items()->get()->each(function ($item) use ($placedAt): void {
            $item->forceFill([
                'created_at' => $placedAt,
                'updated_at' => $placedAt,
            ])->save();
        });

        $order->stockMovements()->get()->each(fn (StockMovement $movement) => $this->retimeMovement($movement, $placedAt));

        AuditLog::query()
            ->where('subject_type', $order->getMorphClass())
            ->where('subject_id', $order->getKey())
            ->update([
                'created_at' => $placedAt,
                'updated_at' => $placedAt,
            ]);
    }

    private function retimeMovement(StockMovement $movement, Carbon $occurredAt): void
    {
        $movement->forceFill([
            'occurred_at' => $occurredAt,
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ])->save();

        AuditLog::query()
            ->where('subject_type', $movement->getMorphClass())
            ->where('subject_id', $movement->getKey())
            ->update([
                'created_at' => $occurredAt,
                'updated_at' => $occurredAt,
            ]);
    }
}
