<?php

namespace App\Http\Controllers\Storefront\Cart;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\Cart\AddToCartRequest;
use App\Http\Requests\Storefront\Cart\UpdateCartItemRequest;
use App\Models\Cart\CartItem;
use App\Models\Catalog\ProductVariant;
use App\Services\Storefront\CartService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class CartController extends Controller
{
    public function index(CartService $cartService): View
    {
        return view('storefront.cart.index', [
            'summary' => $cartService->summary(),
        ]);
    }

    public function store(AddToCartRequest $request, CartService $cartService): RedirectResponse
    {
        $variant = ProductVariant::query()->with('product')->findOrFail($request->integer('variant_id'));
        $cartService->addVariant($variant, $request->integer('quantity'));

        return redirect()->back()
            ->with('toast', [
                'type' => 'success',
                'title' => "{$variant->product->name} added to cart",
                'message' => "{$variant->name} - Qty {$request->integer('quantity')}",
            ]);
    }

    public function update(UpdateCartItemRequest $request, CartItem $item, CartService $cartService): RedirectResponse
    {
        $cartService->updateQuantity($item, $request->integer('quantity'));

        return redirect()->route('storefront.cart.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Shopping bag updated',
                'message' => 'Your cart quantity has been refreshed.',
            ]);
    }

    public function destroy(CartItem $item, CartService $cartService): RedirectResponse
    {
        $cartService->removeItem($item);

        return redirect()->route('storefront.cart.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Item removed',
                'message' => 'The selected item has been removed from your cart.',
            ]);
    }
}
