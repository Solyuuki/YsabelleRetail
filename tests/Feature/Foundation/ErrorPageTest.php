<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

test('the 403 page uses the branded error experience', function () {
    $this->get(route('admin.dashboard'))
        ->assertRedirect(route('login'));

    Route::middleware('web')->get('/test-forbidden', fn () => abort(403));

    $this->get('/test-forbidden')
        ->assertForbidden()
        ->assertSeeText('Access denied')
        ->assertSeeText('Go to sign in');
});

test('the 404 page uses the branded error experience', function () {
    $this->get('/this-route-does-not-exist')
        ->assertNotFound()
        ->assertSeeText('Page not found')
        ->assertSeeText('Browse storefront');
});

test('the 419 page uses the branded error experience', function () {
    config()->set('app.debug', false);

    Route::middleware('web')->post('/test-session-expired', function () {
        throw new TokenMismatchException('CSRF token mismatch.');
    });

    $this->post('/test-session-expired')
        ->assertStatus(419)
        ->assertSeeText('Session expired')
        ->assertSeeText('Sign in again');
});

test('the 429 page uses the branded throttling experience', function () {
    config()->set('app.debug', false);

    Route::middleware(['web', 'throttle:1,1'])->get('/test-throttled', fn () => 'ok');

    $this->get('/test-throttled')->assertOk();

    $this->get('/test-throttled')
        ->assertStatus(429)
        ->assertSeeText('Too many attempts detected')
        ->assertSeeText('Contact support');
});

test('the 500 page uses the branded error experience', function () {
    config()->set('app.debug', false);

    Route::middleware('web')->get('/test-server-error', function () {
        throw new RuntimeException('Boom');
    });

    $this->get('/test-server-error')
        ->assertStatus(500)
        ->assertSeeText('Something went wrong')
        ->assertSeeText('Return to storefront')
        ->assertDontSeeText('RuntimeException')
        ->assertDontSeeText('Boom');
});

test('the 503 page uses the branded maintenance experience', function () {
    config()->set('app.debug', false);

    Route::middleware('web')->get('/test-maintenance', fn () => abort(503));

    $this->get('/test-maintenance')
        ->assertStatus(503)
        ->assertSeeText('We are preparing the store')
        ->assertSeeText('Email support');
});

test('the shared error layout renders safely without auth dependencies', function () {
    $html = view('errors.layout', [
        'title' => 'Test error',
        'status' => '500',
        'headline' => 'Test headline',
        'copy' => 'Test copy',
        'actions' => [
            ['label' => 'Return to storefront', 'url' => route('storefront.home'), 'variant' => 'primary'],
        ],
    ])->render();

    expect($html)
        ->toContain('Test headline')
        ->toContain('Return to storefront')
        ->toContain('brand/yr-logo-full-transparent.png')
        ->toContain('object-fit: contain;')
        ->toContain('No private system details are shown on this page.');
});

test('fallback behavior does not override auth recovery routes', function () {
    $this->get(route('password.request'))
        ->assertOk()
        ->assertSeeText('Reset your password');

    $this->get(route('password.reset', ['token' => 'example-token', 'email' => 'reset@example.com']))
        ->assertOk()
        ->assertSeeText('Choose a new password');

    $this->get(route('verification.notice'))
        ->assertRedirect(route('login'));
});
