<?php

namespace App\Services\Storefront;

use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Catalog\ProductVariant;
use App\Models\User;
use App\Services\Catalog\ProductAvailabilityService;
use App\Services\Inventory\InventoryManager;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;
use Throwable;

class CartService
{
    private const GUEST_CART_SESSION_KEY = 'storefront.cart_guest_session_id';

    public function __construct(
        private readonly Request $request,
        private readonly AuthFactory $auth,
        private readonly InventoryManager $inventoryManager,
        private readonly ProductAvailabilityService $availability,
    ) {}

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
        $variant->loadMissing(['product', 'inventoryItem']);
        $cart = $this->activeCart();
        $existingLine = $cart?->items
            ->firstWhere('product_variant_id', $variant->id);
        $requestedQuantity = ($existingLine?->quantity ?? 0) + $quantity;

        $this->inventoryManager->ensureSufficientStock($variant, $requestedQuantity);
        $cart ??= $this->currentCart();

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
        $item->loadMissing(['variant.product', 'variant.inventoryItem']);
        $this->inventoryManager->ensureSufficientStock($item->variant, $quantity);

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

        $items = $this->annotateInventoryStatus($cart->items);
        $inventoryIssues = $items
            ->filter(fn (CartItem $item): bool => (bool) data_get($item->inventory_status, 'has_issue', false))
            ->values();

        $subtotal = (float) $items->sum(fn (CartItem $item): float => (float) $item->line_total);
        $shipping = $subtotal >= 5000 || $subtotal === 0.0 ? 0.0 : 350.0;
        $total = $subtotal + $shipping;

        return [
            'cart' => $cart,
            'items' => $items,
            'item_count' => (int) $items->sum('quantity'),
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'total' => $total,
            'is_empty' => $items->isEmpty(),
            'has_inventory_issues' => $inventoryIssues->isNotEmpty(),
            'inventory_issues' => $inventoryIssues,
        ];
    }

    public function itemCount(): int
    {
        return $this->summary()['item_count'];
    }

    public function mergeGuestCartFor(User $user): ?Cart
    {
        if (! $this->cartTablesExist()) {
            return null;
        }

        $guestSessionId = $this->guestCartSessionId();

        if (! is_string($guestSessionId) || trim($guestSessionId) === '') {
            return $this->activeCart();
        }

        return DB::transaction(function () use ($guestSessionId, $user): ?Cart {
            $guestCart = Cart::query()
                ->with(['items.variant.product.category', 'items.variant.inventoryItem'])
                ->where('status', 'active')
                ->whereNull('user_id')
                ->where('session_id', $guestSessionId)
                ->first();

            if (! $guestCart) {
                $this->forgetGuestCartSessionId();

                return $this->activeCart();
            }

            $userCart = Cart::query()
                ->with(['items.variant.product.category', 'items.variant.inventoryItem'])
                ->where('status', 'active')
                ->where('user_id', $user->id)
                ->first();

            if (! $userCart) {
                $guestCart->forceFill([
                    'user_id' => $user->id,
                    'session_id' => null,
                    'expires_at' => now()->addDays(7),
                ])->save();

                $this->forgetGuestCartSessionId();

                return $this->freshCart($guestCart);
            }

            foreach ($guestCart->items as $guestItem) {
                $this->mergeGuestItemIntoUserCart($userCart, $guestItem);
            }

            $guestCart->items()->delete();
            $guestCart->delete();
            $this->forgetGuestCartSessionId();

            return $this->freshCart($userCart);
        });
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
            $this->rememberGuestCartSessionId($session->getId());
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

    private function annotateInventoryStatus(Collection $items): Collection
    {
        return $items->map(function (CartItem $item): CartItem {
            $status = $this->availability->forRequestedQuantity($item->variant, (int) $item->quantity);

            if (($status['state'] ?? null) === ProductAvailabilityService::STATE_LOW_STOCK && ! ($status['has_issue'] ?? false)) {
                $status['message'] = 'This item is available in limited stock.';
            }

            $item->setAttribute('inventory_status', $status);

            return $item;
        });
    }

    private function cartTablesExist(): bool
    {
        try {
            return Schema::hasTable('carts') && Schema::hasTable('cart_items');
        } catch (Throwable) {
            return false;
        }
    }

    private function mergeGuestItemIntoUserCart(Cart $userCart, CartItem $guestItem): void
    {
        $guestItem->loadMissing(['variant.product', 'variant.inventoryItem']);

        $targetItem = $userCart->items()
            ->where('product_variant_id', $guestItem->product_variant_id)
            ->first();

        if (! $targetItem) {
            $userCart->items()->create([
                'product_variant_id' => $guestItem->product_variant_id,
                'quantity' => $guestItem->quantity,
                'unit_price' => $guestItem->unit_price,
                'line_total' => $guestItem->line_total,
                'metadata' => $guestItem->metadata,
            ]);

            return;
        }

        $mergedQuantity = $this->mergeableQuantity(
            $guestItem->variant,
            (int) $targetItem->quantity,
            (int) $guestItem->quantity,
        );
        $unitPrice = (float) ($targetItem->unit_price ?: $guestItem->unit_price);

        $targetItem->forceFill([
            'quantity' => $mergedQuantity,
            'unit_price' => $unitPrice,
            'line_total' => $mergedQuantity * $unitPrice,
            'metadata' => $targetItem->metadata ?: $guestItem->metadata,
        ])->save();
    }

    private function mergeableQuantity(ProductVariant $variant, int $existingQuantity, int $guestQuantity): int
    {
        $availability = $this->availability->forVariant($variant);

        if (
            ! ($availability['inventory_tracked'] ?? true)
            || ($availability['allow_backorder'] ?? false) === true
        ) {
            return $existingQuantity + $guestQuantity;
        }

        $availableQuantity = max(0, (int) ($availability['available_quantity'] ?? 0));

        return min(
            $existingQuantity + $guestQuantity,
            max($existingQuantity, $availableQuantity),
        );
    }

    private function guestCartSessionId(): ?string
    {
        $sessionId = $this->request->session()->get(self::GUEST_CART_SESSION_KEY);

        return is_string($sessionId) && trim($sessionId) !== ''
            ? $sessionId
            : null;
    }

    private function rememberGuestCartSessionId(string $sessionId): void
    {
        if (trim($sessionId) === '') {
            return;
        }

        $this->request->session()->put(self::GUEST_CART_SESSION_KEY, $sessionId);
    }

    private function forgetGuestCartSessionId(): void
    {
        $this->request->session()->forget(self::GUEST_CART_SESSION_KEY);
    }
}
