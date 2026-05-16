<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\SocialAuthService;
use App\Services\Auth\AuthSystemHealthService;
use App\Services\Storefront\CartService;
use App\Support\Auth\AuthenticatedRedirector;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function create(
        Request $request,
        SocialAuthService $socialAuth,
        AuthenticatedRedirector $redirector,
    ): View
    {
        $redirector->rememberLoginContext($request);

        return view('auth.login', [
            'isAdminPortal' => $redirector->isAdminPortal($request),
            'portalIntent' => $redirector->currentPortal($request),
            'intendedUrl' => $request->session()->get('url.intended'),
            'socialProviders' => $socialAuth->providerButtons($request),
        ]);
    }

    public function store(
        LoginRequest $request,
        CartService $cartService,
        AuthenticatedRedirector $redirector,
        AuthSystemHealthService $authHealth,
    ): \Illuminate\Http\RedirectResponse
    {
        $redirector->rememberLoginContext($request);
        $request->ensureIsNotRateLimited();
        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');
        $candidateUser = $request->candidateUser();

        if ($candidateUser && ! $candidateUser->hasLocalPassword()) {
            $request->hitRateLimiter();

            throw ValidationException::withMessages([
                'email' => 'This account uses social sign-in. Continue with Google, Microsoft, or GitHub, or set a password first.',
            ]);
        }

        if (! Auth::attempt($credentials, $remember)) {
            $request->hitRateLimiter();

            throw ValidationException::withMessages([
                'email' => 'Invalid email or password.',
            ]);
        }

        $request->session()->regenerate();
        $authHealth->reconcileUserRole($request->user());

        if (! $request->user()?->isActive()) {
            Auth::logout();
            $redirector->clearLoginContext($request);
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'This account is inactive. Please contact an administrator.',
            ]);
        }

        if ($message = $redirector->portalAccessViolationMessage($request, $request->user())) {
            Auth::logout();
            $redirector->clearLoginContext($request);
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => $message,
            ]);
        }

        $request->clearRateLimiter();
        $cartService->mergeGuestCartFor($request->user());

        return $redirector->redirectAfterLogin($request, $request->user());
    }
}
