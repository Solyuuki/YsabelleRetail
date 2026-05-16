<?php

use App\Http\Middleware\EnsureUserHasAdminRole;
use App\Http\Middleware\EnsureUserHasCustomerRole;
use App\Http\Middleware\PreventBackHistoryCache;
use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserHasAdminRole::class,
            'customer' => EnsureUserHasCustomerRole::class,
            'prevent-back-history' => PreventBackHistoryCache::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TokenMismatchException $exception, Request $request) {
            $recentLogoutAt = (int) $request->session()->get('recent_logout_completed_at', 0);
            $recentlyLoggedOut = $recentLogoutAt > 0
                && (now()->timestamp - $recentLogoutAt) <= 15;

            if (! $recentlyLoggedOut || $request->user() !== null) {
                return null;
            }

            if ($request->routeIs('logout')) {
                $response = redirect()->route('storefront.home')
                    ->with('toast', [
                        'type' => 'success',
                        'title' => 'Signed out',
                        'message' => 'You have been signed out of Ysabelle Retail.',
                    ]);

                $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0');
                $response->headers->set('Pragma', 'no-cache');
                $response->headers->set('Expires', 'Fri, 01 Jan 1990 00:00:00 GMT');

                return $response;
            }

            if ($request->routeIs('storefront.account.review-claims.switch-account')) {
                $token = (string) $request->route('token');

                return redirect()
                    ->route('login', [
                        'intended' => route('storefront.account.review-claims.show', ['token' => $token]),
                    ])
                    ->with('status', 'Sign in with the email address that received this claim link to continue.');
            }

            return null;
        });
    })->create();
