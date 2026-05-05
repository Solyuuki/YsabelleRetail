<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\SocialAuthException;
use App\Services\Auth\SocialAuthService;
use App\Support\Auth\AuthenticatedRedirector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SocialAuthController extends Controller
{
    public function redirect(
        Request $request,
        string $provider,
        SocialAuthService $socialAuth,
    ): RedirectResponse {
        try {
            return $socialAuth->redirect($provider, $request);
        } catch (SocialAuthException $exception) {
            return $this->redirectWithOAuthError('Social sign-in unavailable', $exception);
        }
    }

    public function callback(
        Request $request,
        string $provider,
        SocialAuthService $socialAuth,
        AuthenticatedRedirector $redirector,
    ): RedirectResponse {
        try {
            $user = $socialAuth->resolveCallbackUser($provider, $request);
        } catch (SocialAuthException $exception) {
            return $this->redirectWithOAuthError('Social sign-in failed', $exception);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return $redirector->redirectAfterLogin($request, $user);
    }

    private function redirectWithOAuthError(
        string $title,
        SocialAuthException $exception,
    ): RedirectResponse {
        Log::log($exception->reportLevel(), 'Social OAuth flow failed.', $exception->context());

        return redirect()->route('login')
            ->with('toast', [
                'type' => 'error',
                'title' => $title,
                'message' => $exception->getMessage(),
            ]);
    }
}
