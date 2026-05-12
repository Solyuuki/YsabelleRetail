<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\Auth\CustomerAccountService;
use App\Services\Auth\SocialAuthService;
use App\Support\Auth\AuthenticatedRedirector;
use Illuminate\Contracts\View\View;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    public function create(
        Request $request,
        SocialAuthService $socialAuth,
        AuthenticatedRedirector $redirector,
    ): View
    {
        $redirector->rememberLoginContext($request);

        return view('auth.register', [
            'socialProviders' => $socialAuth->providerButtons($request),
        ]);
    }

    public function store(
        RegisterRequest $request,
        CustomerAccountService $customerAccounts,
        AuthenticatedRedirector $redirector,
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

            if ($this->shouldContinueToClaimDestination($request)) {
                return redirect()->intended(route('storefront.account.index'))
                    ->with('status', 'We sent a verification link to your email address.');
            }

            return redirect()->route('verification.notice')
                ->with('status', 'We sent a verification link to your email address.');
        }

        return redirect()->intended(route('storefront.account.index'))
            ->with('toast', [
                'type' => 'success',
                'title' => 'Account created',
                'message' => 'Your Ysabelle Retail account is ready.',
            ]);
    }

    private function shouldContinueToClaimDestination(Request $request): bool
    {
        $intended = $request->session()->get('url.intended');
        $path = is_string($intended) ? parse_url($intended, PHP_URL_PATH) : null;

        return is_string($path) && Str::startsWith($path, '/account/review-claims/');
    }
}
