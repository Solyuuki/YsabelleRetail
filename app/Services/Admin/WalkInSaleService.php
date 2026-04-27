<?php

namespace App\Services\Admin;

use App\Models\Catalog\ProductVariant;
use App\Models\Orders\Order;
use App\Models\User;
use App\Services\Inventory\InventoryManager;
use App\Support\OrderNumberGenerator;
use Illuminate\Support\Facades\DB;

class WalkInSaleService
{
    public function __construct(
        private readonly InventoryManager $inventoryManager,
        private readonly OrderNumberGenerator $orderNumbers,
    ) {
    }

    public function create(array $payload, User $cashier): Order
    {
        return DB::transaction(function () use ($payload, $cashier): Order {
            $lines = collect($payload['lines']);
            $variants = ProductVariant::query()
                ->with(['product', 'inventoryItem'])
                ->whereIn('id', $lines->pluck('variant_id'))
                ->get()
                ->keyBy('id');

            $subtotal = 0.0;

            foreach ($lines as $line) {
                $variant = $variants->get($line['variant_id']);
                $subtotal += $line['quantity'] * (float) $variant->price;
                $this->inventoryManager->ensureSufficientStock($variant, $line['quantity']);
            }

            $paymentStatus = $payload['payment_status'];
            $order = Order::query()->create([
                'user_id' => null,
                'source' => 'walk_in',
                'handled_by_user_id' => $cashier->id,
                'order_number' => $this->orderNumbers->generate('YSP'),
                'status' => $paymentStatus === 'paid' ? 'completed' : 'pending',
                'payment_status' => $paymentStatus,
                'fulfillment_status' => 'fulfilled',
                'currency' => 'PHP',
                'subtotal_amount' => $subtotal,
                'discount_amount' => 0,
                'shipping_amount' => 0,
                'tax_amount' => 0,
                'grand_total' => $subtotal,
                'placed_at' => now(),
                'notes' => $payload['notes'] ?: null,
                'customer_name' => $payload['customer_name'] ?: 'Walk-in Customer',
                'customer_email' => null,
                'customer_phone' => $payload['customer_phone'] ?: null,
                'shipping_city' => null,
                'shipping_address_line' => null,
                'shipping_postal_code' => null,
                'payment_method' => $payload['payment_method'],
                'metadata' => [
                    'walk_in' => true,
                ],
            ]);

            foreach ($lines as $line) {
                $variant = $variants->get($line['variant_id']);
                $price = (float) $variant->price;

                $order->items()->create([
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'product_name' => $variant->product->name,
                    'variant_name' => $variant->name,
                    'sku' => $variant->sku,
                    'quantity' => $line['quantity'],
                    'unit_price' => $price,
                    'line_total' => $line['quantity'] * $price,
                    'metadata' => [
                        'source' => 'walk_in',
                        'option_values' => $variant->option_values,
                    ],
                ]);

                $this->inventoryManager->deductForWalkInSale(
                    variant: $variant,
                    quantity: $line['quantity'],
                    order: $order,
                    actor: $cashier,
                    metadata: ['payment_method' => $payload['payment_method']]
                );
            }

            $order->payments()->create([
                'provider' => $payload['payment_method'],
                'provider_reference' => null,
                'status' => $paymentStatus === 'paid' ? 'succeeded' : 'pending',
                'amount' => $subtotal,
                'currency' => 'PHP',
                'paid_at' => $paymentStatus === 'paid' ? now() : null,
                'metadata' => [
                    'source' => 'walk_in',
                    'cashier' => $cashier->email,
                    'method' => $payload['payment_method'],
                ],
            ]);

            return $order->fresh(['items', 'payments', 'handledBy']);
        });
    }
}
