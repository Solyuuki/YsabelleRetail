<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\Auth\CustomerAccountService;
use App\Services\Auth\SocialAuthService;
use Illuminate\Contracts\View\View;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    public function create(Request $request, SocialAuthService $socialAuth): View
    {
        return view('auth.register', [
            'socialProviders' => $socialAuth->providerButtons($request),
        ]);
    }

    public function store(
        RegisterRequest $request,
        CustomerAccountService $customerAccounts,
    ): RedirectResponse {
        $user = $customerAccounts->register(
            name: $request->string('name')->toString(),
            email: $request->string('email')->toString(),
            password: $request->string('password')->toString(),
        );

        Auth::login($user);
        $request->session()->regenerate();

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();

            return redirect()->route('verification.notice')
                ->with('status', 'We sent a verification link to your email address.');
        }

        return redirect()->route('storefront.account.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Account created',
                'message' => 'Your Ysabelle Retail account is ready.',
            ]);
    }
}
