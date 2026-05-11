<?php

namespace App\Support\Auth;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuthenticatedRedirector
{
    private const ADMIN_PORTAL = 'admin';

    private const LOGIN_PORTAL_SESSION_KEY = 'auth.login_portal';

    public function rememberLoginContext(Request $request): void
    {
        $portal = $this->normalizePortal($request->query('portal'));

        if ($portal === self::ADMIN_PORTAL) {
            $request->session()->put('url.intended', route('admin.dashboard'));
            $request->session()->put(self::LOGIN_PORTAL_SESSION_KEY, self::ADMIN_PORTAL);

            return;
        }

        if ($intended = $this->normalizeIntendedUrl($request->query('intended'))) {
            $request->session()->put('url.intended', $intended);
        }

        if ($this->isAdminDestination($request->session()->get('url.intended'))) {
            $request->session()->put(self::LOGIN_PORTAL_SESSION_KEY, self::ADMIN_PORTAL);

            return;
        }

        $request->session()->forget(self::LOGIN_PORTAL_SESSION_KEY);
    }

    public function isAdminPortal(Request $request): bool
    {
        return $request->session()->get(self::LOGIN_PORTAL_SESSION_KEY) === self::ADMIN_PORTAL
            || $this->isAdminDestination($request->session()->get('url.intended'));
    }

    public function redirectAfterLogin(Request $request, User $user): RedirectResponse
    {
        $request->session()->forget(self::LOGIN_PORTAL_SESSION_KEY);

        return redirect()->intended($this->defaultDestinationFor($user))
            ->with('toast', [
                'type' => 'success',
                'title' => 'Welcome back',
                'message' => 'You are now signed in to Ysabelle Retail.',
            ]);
    }

    public function adminAccessUrl(): string
    {
        return route('login', ['portal' => self::ADMIN_PORTAL]);
    }

    public function loginUrlForCurrentPortal(Request $request): string
    {
        return $this->isAdminPortal($request)
            ? $this->adminAccessUrl()
            : route('login');
    }

    public function portalAccessViolationMessage(Request $request, User $user): ?string
    {
        if ($this->isAdminPortal($request) && ! $user->isAdmin()) {
            return 'This account is not authorized for the admin login portal.';
        }

        if (! $this->isAdminPortal($request) && $user->isAdmin()) {
            return 'Admin accounts must sign in through the admin login portal only.';
        }

        return null;
    }

    public function clearLoginContext(Request $request, bool $forgetIntended = true): void
    {
        $keys = [self::LOGIN_PORTAL_SESSION_KEY];

        if ($forgetIntended) {
            $keys[] = 'url.intended';
        }

        $request->session()->forget($keys);
    }

    private function defaultDestinationFor(User $user): string
    {
        return match (true) {
            $user->isAdmin() => route('admin.dashboard'),
            $user->isCustomer() => route('storefront.account.index'),
            default => route('storefront.home'),
        };
    }

    private function normalizePortal(mixed $portal): ?string
    {
        if (! is_string($portal)) {
            return null;
        }

        $portal = Str::lower(trim($portal));

        return $portal === self::ADMIN_PORTAL ? self::ADMIN_PORTAL : null;
    }

    private function isAdminDestination(mixed $intended): bool
    {
        return is_string($intended) && $intended === route('admin.dashboard');
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
