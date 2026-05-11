<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\Checkout\CheckoutDraftRequest;
use App\Http\Requests\Storefront\Checkout\CheckoutRequest;
use App\Services\Storefront\CartService;
use App\Services\Storefront\CheckoutDraftService;
use App\Services\Storefront\CheckoutService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function create(
        CartService $cartService,
        CheckoutDraftService $drafts,
        Request $request,
    ): View|RedirectResponse
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

        $user = $request->user()->loadMissing('profile');
        $profile = $user->profile;
        $checkoutDraft = $drafts->dataFor($user);
        $checkoutDefaults = [
            'full_name' => $user->name,
            'email' => strtolower($user->email),
            'phone' => $profile?->mobile_number ?: $profile?->phone,
            'city' => $profile?->shipping_city,
            'address' => $profile?->shipping_address_line,
            'postal_code' => $profile?->shipping_postal_code,
            'order_notes' => null,
            'payment_method' => 'cod',
        ];

        if (is_array($checkoutDraft)) {
            $checkoutDefaults = array_merge($checkoutDefaults, $checkoutDraft);
        }

        return view('storefront.checkout.create', [
            'summary' => $summary,
            'user' => $user,
            'checkoutDefaults' => $checkoutDefaults,
            'checkoutDraftRestored' => is_array($checkoutDraft),
            'checkoutDraftTtlDays' => $drafts->expiresInDays(),
        ]);
    }

    public function store(
        CheckoutRequest $request,
        CartService $cartService,
        CheckoutDraftService $drafts,
        CheckoutService $checkoutService,
    ): RedirectResponse {
        $cart = $cartService->currentCart();
        $cart->load(['items.variant.product']);

        $order = $checkoutService->placeOrder($cart, $request->user(), $request->validated());
        $drafts->clear($request->user());
        $cartService->clear($cart);

        return redirect()->route('storefront.account.index')
            ->with('toast', [
                'type' => 'success',
                'title' => "Order {$order->order_number} confirmed",
                'message' => "We'll send updates to {$order->customer_email}.",
            ])
            ->with('order_success_number', $order->order_number);
    }

    public function saveDraft(
        CheckoutDraftRequest $request,
        CheckoutDraftService $drafts,
    ): JsonResponse {
        $draft = $drafts->save($request->user(), $request->validated());

        return response()->json([
            'status' => $draft ? 'saved' : 'cleared',
            'expires_at' => $draft?->expires_at?->toIso8601String(),
        ]);
    }
}
