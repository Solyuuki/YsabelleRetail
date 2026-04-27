<?php

namespace App\Services\Storefront;

use App\Models\Cart\Cart;
use App\Models\Orders\Order;
use App\Models\User;
use App\Services\Inventory\InventoryManager;
use App\Support\Storefront\ProductMediaResolver;
use App\Support\OrderNumberGenerator;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    public function __construct(
        private readonly ProductMediaResolver $productMedia,
        private readonly InventoryManager $inventoryManager,
        private readonly OrderNumberGenerator $orderNumbers,
    ) {
    }

    public function placeOrder(Cart $cart, User $user, array $payload): Order
    {
        abort_if($cart->items->isEmpty(), 422, 'Your cart is empty.');

        return DB::transaction(function () use ($cart, $user, $payload): Order {
            $cart->loadMissing(['items.variant.product', 'items.variant.inventoryItem']);

            foreach ($cart->items as $item) {
                $this->inventoryManager->ensureSufficientStock($item->variant, (int) $item->quantity);
            }

            $subtotal = (float) $cart->items->sum(fn ($item): float => (float) $item->line_total);
            $shipping = $subtotal >= 5000 ? 0.0 : 350.0;
            $grandTotal = $subtotal + $shipping;
            $paymentMethod = $payload['payment_method'];
            $isSimulatedCard = $paymentMethod === 'card_simulated';

            $order = Order::query()->create([
                'user_id' => $user->id,
                'source' => 'online',
                'handled_by_user_id' => null,
                'order_number' => $this->orderNumbers->generate(),
                'status' => 'pending',
                'payment_status' => $isSimulatedCard ? 'paid' : 'unpaid',
                'fulfillment_status' => 'unfulfilled',
                'currency' => 'PHP',
                'subtotal_amount' => $subtotal,
                'discount_amount' => 0,
                'shipping_amount' => $shipping,
                'tax_amount' => 0,
                'grand_total' => $grandTotal,
                'placed_at' => now(),
                'notes' => $payload['order_notes'] ?? null,
                'customer_name' => $payload['full_name'],
                'customer_email' => $payload['email'],
                'customer_phone' => $payload['phone'],
                'shipping_city' => $payload['city'],
                'shipping_address_line' => $payload['address'],
                'shipping_postal_code' => $payload['postal_code'],
                'payment_method' => $paymentMethod,
                'metadata' => [
                    'source' => 'online',
                ],
            ]);

            foreach ($cart->items as $item) {
                $variant = $item->variant;
                $product = $variant?->product;

                $order->items()->create([
                    'product_id' => $product?->id,
                    'product_variant_id' => $variant?->id,
                    'product_name' => $product?->name ?? 'Unknown Product',
                    'variant_name' => $variant?->name,
                    'sku' => $variant?->sku,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total,
                    'metadata' => [
                        'product_slug' => $product?->slug,
                        'option_values' => $variant?->option_values,
                        'product_image_url' => $this->productMedia->imageUrlFor($product),
                        'product_image_alt' => $this->productMedia->altTextFor($product, $product?->name),
                    ],
                ]);

                $this->inventoryManager->deductForOnlineSale(
                    variant: $variant,
                    quantity: (int) $item->quantity,
                    order: $order,
                    actor: $user,
                    metadata: ['checkout' => true]
                );
            }

            $order->payments()->create([
                'provider' => $isSimulatedCard ? 'card-simulated' : 'cash-on-delivery',
                'provider_reference' => $isSimulatedCard ? strtoupper(bin2hex(random_bytes(6))) : null,
                'status' => $isSimulatedCard ? 'succeeded' : 'pending',
                'amount' => $grandTotal,
                'currency' => 'PHP',
                'paid_at' => $isSimulatedCard ? now() : null,
                'metadata' => [
                    'method' => $paymentMethod,
                    'flow' => $isSimulatedCard ? 'simulated-card' : 'offline-manual',
                    'card_last4' => $isSimulatedCard ? substr((string) ($payload['card_number'] ?? ''), -4) : null,
                    'cardholder_name' => $isSimulatedCard ? $payload['cardholder_name'] : null,
                    'simulated' => $isSimulatedCard,
                ],
            ]);

            return $order->load(['items', 'payments']);
        });
    }
}
