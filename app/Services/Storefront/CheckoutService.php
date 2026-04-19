<?php

namespace App\Services\Storefront;

use App\Models\Cart\Cart;
use App\Models\Orders\Order;
use App\Models\User;
use App\Support\Storefront\ProductMediaResolver;
use Illuminate\Support\Str;

class CheckoutService
{
    public function __construct(
        private readonly ProductMediaResolver $productMedia,
    ) {
    }

    public function placeOrder(Cart $cart, User $user, array $payload): Order
    {
        abort_if($cart->items->isEmpty(), 422, 'Your cart is empty.');

        $subtotal = (float) $cart->items->sum(fn ($item): float => (float) $item->line_total);
        $shipping = $subtotal >= 5000 ? 0.0 : 350.0;
        $grandTotal = $subtotal + $shipping;

        $order = Order::query()->create([
            'user_id' => $user->id,
            'order_number' => $this->generateOrderNumber(),
            'status' => 'pending',
            'payment_status' => 'pending',
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
            'payment_method' => $payload['payment_method'],
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
        }

        $order->payments()->create([
            'provider' => $payload['payment_method'] === 'card' ? 'card-simulated' : 'cash-on-delivery',
            'provider_reference' => Str::upper(Str::random(12)),
            'status' => 'pending',
            'amount' => $grandTotal,
            'currency' => 'PHP',
            'metadata' => [
                'method' => $payload['payment_method'],
            ],
        ]);

        return $order->load(['items', 'payments']);
    }

    private function generateOrderNumber(): string
    {
        return 'YSB-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
    }
}
