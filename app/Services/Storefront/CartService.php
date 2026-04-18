<?php

namespace App\Services\Storefront;

use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Catalog\ProductVariant;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CartService
{
    public function __construct(
        private readonly Request $request,
        private readonly AuthFactory $auth,
    ) {
    }

    public function currentCart(): Cart
    {
        return $this->findCart(createIfMissing: true);
    }

    public function activeCart(): ?Cart
    {
        return $this->findCart(createIfMissing: false);
    }

    public function addVariant(ProductVariant $variant, int $quantity): Cart
    {
        $cart = $this->currentCart();

        $line = $cart->items()
            ->where('product_variant_id', $variant->id)
            ->first();

        $unitPrice = $variant->price ?? $variant->product->base_price;

        if ($line) {
            $line->quantity += $quantity;
            $line->line_total = $line->quantity * $unitPrice;
            $line->save();
        } else {
            $cart->items()->create([
                'product_variant_id' => $variant->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $quantity * $unitPrice,
                'metadata' => [
                    'product_slug' => $variant->product->slug,
                ],
            ]);
        }

        return $this->freshCart($cart);
    }

    public function updateQuantity(CartItem $item, int $quantity): Cart
    {
        if ($quantity <= 0) {
            return $this->removeItem($item);
        }

        $this->authorizeItem($item);

        $item->update([
            'quantity' => $quantity,
            'line_total' => $quantity * (float) $item->unit_price,
        ]);

        return $this->freshCart($item->cart);
    }

    public function removeItem(CartItem $item): Cart
    {
        $this->authorizeItem($item);
        $cart = $item->cart;
        $item->delete();

        return $this->freshCart($cart);
    }

    public function clear(Cart $cart): void
    {
        $cart->items()->delete();
    }

    public function summary(?Cart $cart = null): array
    {
        $cart ??= $this->activeCart();

        if (! $cart) {
            return [
                'cart' => null,
                'items' => collect(),
                'item_count' => 0,
                'subtotal' => 0.0,
                'shipping' => 0.0,
                'total' => 0.0,
                'is_empty' => true,
            ];
        }

        $subtotal = (float) $cart->items->sum(fn (CartItem $item): float => (float) $item->line_total);
        $shipping = $subtotal >= 5000 || $subtotal === 0.0 ? 0.0 : 350.0;
        $total = $subtotal + $shipping;

        return [
            'cart' => $cart,
            'items' => $cart->items,
            'item_count' => (int) $cart->items->sum('quantity'),
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'total' => $total,
            'is_empty' => $cart->items->isEmpty(),
        ];
    }

    public function itemCount(): int
    {
        return $this->summary()['item_count'];
    }

    private function findCart(bool $createIfMissing): ?Cart
    {
        if (! $this->cartTablesExist()) {
            return null;
        }

        $session = $this->request->session();
        $session->start();

        $query = Cart::query()
            ->with(['items.variant.product.category', 'items.variant.inventoryItem'])
            ->where('status', 'active');

        $user = $this->auth->guard('web')->user();

        if ($user) {
            $query->where('user_id', $user->id);
        } else {
            $query->where('session_id', $session->getId());
        }

        $cart = $query->first();

        if ($cart || ! $createIfMissing) {
            return $cart;
        }

        return Cart::query()->create([
            'user_id' => $user?->id,
            'session_id' => $user ? null : $session->getId(),
            'status' => 'active',
            'currency' => 'PHP',
            'expires_at' => now()->addDays(7),
        ])->load(['items.variant.product.category', 'items.variant.inventoryItem']);
    }

    private function authorizeItem(CartItem $item): void
    {
        abort_unless($item->cart_id === $this->currentCart()->id, 403);
    }

    private function freshCart(Cart $cart): Cart
    {
        return $cart->fresh(['items.variant.product.category', 'items.variant.inventoryItem']);
    }

    private function cartTablesExist(): bool
    {
        try {
            return Schema::hasTable('carts') && Schema::hasTable('cart_items');
        } catch (Throwable) {
            return false;
        }
    }
}
