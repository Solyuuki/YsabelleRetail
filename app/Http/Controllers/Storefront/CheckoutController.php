<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\Checkout\CheckoutRequest;
use App\Services\Storefront\CartService;
use App\Services\Storefront\CheckoutService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function create(CartService $cartService, Request $request): View|RedirectResponse
    {
        $summary = $cartService->summary();

        if ($summary['is_empty']) {
            return redirect()->route('storefront.cart.index')
                ->with('toast', [
                    'type' => 'error',
                    'title' => 'Cart is empty',
                    'message' => 'Add an item before starting checkout.',
                ]);
        }

        return view('storefront.checkout.create', [
            'summary' => $summary,
            'user' => $request->user(),
        ]);
    }

    public function store(
        CheckoutRequest $request,
        CartService $cartService,
        CheckoutService $checkoutService,
    ): RedirectResponse {
        $cart = $cartService->currentCart();
        $cart->load(['items.variant.product']);

        $order = $checkoutService->placeOrder($cart, $request->user(), $request->validated());
        $cartService->clear($cart);

        return redirect()->route('storefront.account.index')
            ->with('toast', [
                'type' => 'success',
                'title' => "Order {$order->order_number} confirmed",
                'message' => "We'll send updates to {$order->customer_email}.",
            ])
            ->with('order_success_number', $order->order_number);
    }
}
