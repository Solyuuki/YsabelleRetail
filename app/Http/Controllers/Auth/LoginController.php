<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function create(Request $request): View
    {
        if ($intended = $this->normalizeIntendedUrl($request->query('intended'))) {
            $request->session()->put('url.intended', $intended);
        }

        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
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

        $intended = $request->session()->get('url.intended');
        $adminDashboard = route('admin.dashboard');

        if ($intended === $adminDashboard && ! $request->user()?->isAdmin()) {
            $request->session()->forget('url.intended');

            return redirect()->route('storefront.account.index')
                ->with('toast', [
                    'type' => 'error',
                    'title' => 'Admin area unavailable',
                    'message' => 'Admin access requires an authorized admin account.',
                ]);
        }

        return redirect()->intended(route('storefront.account.index'))
            ->with('toast', [
                'type' => 'success',
                'title' => 'Welcome back',
                'message' => 'You are now signed in to Ysabelle Retail.',
            ]);
    }

    private function normalizeIntendedUrl(mixed $intended): ?string
    {
        if (! is_string($intended)) {
            return null;
        }

        $intended = trim($intended);

        if ($intended === '') {
            return null;
        }

        if (Str::startsWith($intended, '/')) {
            return url($intended);
        }

        if (! filter_var($intended, FILTER_VALIDATE_URL)) {
            return null;
        }

        $appUrl = parse_url(url('/'));
        $targetUrl = parse_url($intended);

        if (($appUrl['scheme'] ?? null) !== ($targetUrl['scheme'] ?? null)) {
            return null;
        }

        if (($appUrl['host'] ?? null) !== ($targetUrl['host'] ?? null)) {
            return null;
        }

        if (($appUrl['port'] ?? null) !== ($targetUrl['port'] ?? null)) {
            return null;
        }

        return $intended;
    }
}
