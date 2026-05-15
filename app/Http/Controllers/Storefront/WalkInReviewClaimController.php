<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Orders\WalkInReviewClaimService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            'switchAccountUrl' => route('storefront.account.review-claims.switch-account', ['token' => $token]),
        ]);
    }

    public function switchAccount(Request $request, string $token): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login', [
                'intended' => route('storefront.account.review-claims.show', ['token' => $token]),
            ])
            ->with('status', 'Sign in with the email address that received this claim link to continue.');
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
