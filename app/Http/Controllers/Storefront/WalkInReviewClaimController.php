<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Orders\WalkInReviewClaimService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WalkInReviewClaimController extends Controller
{
    public function show(Request $request, string $token, WalkInReviewClaimService $claims): View
    {
        $claim = $claims->findByPlainToken($token);
        $status = $claims->statusFor($claim, $request->user());

        return view('storefront.account.review-claim', [
            'claim' => $claim,
            'status' => $status,
            'token' => $token,
            'maskedClaimEmail' => $claim ? $claims->maskedEmail($claim->customer_email) : null,
            'loginUrl' => route('login', ['intended' => $request->fullUrl()]),
            'registerUrl' => route('register', ['intended' => $request->fullUrl()]),
        ]);
    }

    public function store(Request $request, string $token, WalkInReviewClaimService $claims): RedirectResponse
    {
        $claim = $claims->findByPlainToken($token);

        abort_unless($claim, 404);

        $order = $claims->claim($claim, $request->user());

        return redirect()
            ->route('storefront.account.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Walk-in purchase claimed',
                'message' => "Order {$order->order_number} is now linked to your account for verified reviews.",
            ]);
    }
}
