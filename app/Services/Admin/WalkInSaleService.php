<?php

namespace App\Services\Admin;

use App\Events\Admin\OrderPlaced;
use App\Models\Catalog\ProductVariant;
use App\Models\Orders\Order;
use App\Models\User;
use App\Services\Inventory\InventoryManager;
use App\Services\Orders\WalkInReviewClaimService;
use App\Support\OrderNumberGenerator;
use Illuminate\Support\Facades\DB;

class WalkInSaleService
{
    public function __construct(
        private readonly AdminActivityLogger $activityLogger,
        private readonly InventoryManager $inventoryManager,
        private readonly OrderNumberGenerator $orderNumbers,
        private readonly WalkInReviewClaimService $reviewClaims,
    ) {}

    public function create(array $payload, User $cashier): Order
    {
        $order = DB::transaction(function () use ($payload, $cashier): Order {
            $lines = collect($payload['lines']);
            $notes = $payload['notes'] ?? null;
            $customerName = $payload['customer_name'] ?? null;
            $customerEmail = $payload['customer_email'] ?? null;
            $customerPhone = $payload['customer_phone'] ?? null;
            $paymentMethod = $payload['payment_method'];
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

            $discountAmount = min(
                max((float) ($payload['discount_amount'] ?? 0), 0),
                max($subtotal, 0),
            );
            $grandTotal = max($subtotal - $discountAmount, 0);
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
                'discount_amount' => $discountAmount,
                'shipping_amount' => 0,
                'tax_amount' => 0,
                'grand_total' => $grandTotal,
                'placed_at' => now(),
                'notes' => $notes ?: null,
                'customer_name' => $customerName ?: 'Walk-in Customer',
                'customer_email' => $customerEmail ?: null,
                'customer_phone' => $customerPhone ?: null,
                'shipping_city' => null,
                'shipping_address_line' => null,
                'shipping_postal_code' => null,
                'payment_method' => $paymentMethod,
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
                    metadata: ['payment_method' => $paymentMethod]
                );
            }

            $order->payments()->create([
                'provider' => $paymentMethod,
                'provider_reference' => null,
                'status' => $paymentStatus === 'paid' ? 'succeeded' : 'pending',
                'amount' => $grandTotal,
                'currency' => 'PHP',
                'paid_at' => $paymentStatus === 'paid' ? now() : null,
                'metadata' => [
                    'source' => 'walk_in',
                    'cashier' => $cashier->email,
                    'method' => $paymentMethod,
                    'discount_amount' => $discountAmount,
                ],
            ]);

            $order = $order->fresh(['items', 'payments', 'handledBy']);
            $this->activityLogger->recordOrder($order);
            OrderPlaced::dispatch($order);

            return $order;
        });

        $this->reviewClaims->issueAndSendForEligibleOrder($order);

        return $order;
    }
}
