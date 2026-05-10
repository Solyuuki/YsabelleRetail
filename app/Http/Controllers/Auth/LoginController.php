<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\SocialAuthService;
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
            'socialProviders' => $socialAuth->providerButtons($request),
        ]);
    }

    public function store(
        LoginRequest $request,
        AuthenticatedRedirector $redirector,
    ): \Illuminate\Http\RedirectResponse
    {
        $request->ensureIsNotRateLimited();
        $credentials = $request->validated();

        if (! Auth::attempt($credentials, true)) {
            $request->hitRateLimiter();

            throw ValidationException::withMessages([
                'email' => 'Invalid email or password.',
            ]);
        }

        $request->clearRateLimiter();
        $request->session()->regenerate();

        if (! $request->user()?->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'This account is inactive. Please contact an administrator.',
            ]);
        }

        return $redirector->redirectAfterLogin($request, $request->user());
    }
}
