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
        ->assertSeeText('Return home');
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

test('the 500 page uses the branded error experience', function () {
    config()->set('app.debug', false);

    Route::middleware('web')->get('/test-server-error', function () {
        throw new RuntimeException('Boom');
    });

    $this->get('/test-server-error')
        ->assertStatus(500)
        ->assertSeeText('Something went wrong')
        ->assertSeeText('Try again later');
});
