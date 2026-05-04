<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('login page renders the redesigned auth experience', function () {
    foreach (['google', 'microsoft', 'facebook'] as $provider) {
        config()->set("services.{$provider}", [
            'client_id' => null,
            'client_secret' => null,
            'redirect' => null,
        ]);
    }

    $this->get(route('login'))
        ->assertOk()
        ->assertSeeText('Welcome back')
        ->assertSeeText('Continue with Google')
        ->assertSeeText('Continue with Microsoft')
        ->assertSeeText('Continue with Facebook')
        ->assertSeeText('Google sign-in is not configured yet. Please use email and password for now.');
});

test('register page renders the redesigned auth experience', function () {
    foreach (['google', 'microsoft', 'facebook'] as $provider) {
        config()->set("services.{$provider}", [
            'client_id' => null,
            'client_secret' => null,
            'redirect' => null,
        ]);
    }

    $this->get(route('register'))
        ->assertOk()
        ->assertSeeText('Create an account')
        ->assertSeeText('Continue with Google')
        ->assertSeeText('Continue with Microsoft')
        ->assertSeeText('Continue with Facebook')
        ->assertSeeText('Google sign-in is not configured yet. Please use email and password for now.');
});

test('configured providers render a real oauth redirect link', function () {
    $appUrl = (string) config('app.url');
    config()->set('services.google.client_id', 'client-id');
    config()->set('services.google.client_secret', 'client-secret');
    config()->set('services.google.redirect', rtrim($appUrl, '/').'/auth/google/callback');

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('href="'.rtrim($appUrl, '/').'/auth/google/redirect"', escape: false);
});

test('host mismatches disable the affected social provider button with guidance', function () {
    config()->set('services.google.client_id', 'client-id');
    config()->set('services.google.client_secret', 'client-secret');
    config()->set('services.google.redirect', 'http://127.0.0.1:8000/auth/google/callback');

    $this->withServerVariables([
        'HTTP_HOST' => 'localhost:8000',
        'SERVER_PORT' => 8000,
    ])
        ->get(route('login'))
        ->assertOk()
        ->assertSeeText('Google sign-in is configured for http://127.0.0.1:8000/login. Open that URL or align APP_URL and GOOGLE_REDIRECT_URI to the same origin.');
});

test('microsoft host mismatch explains the exact configured login url', function () {
    config()->set('services.microsoft.client_id', 'client-id');
    config()->set('services.microsoft.client_secret', 'client-secret');
    config()->set('services.microsoft.redirect', 'http://127.0.0.1:8000/auth/microsoft/callback');

    $this->withServerVariables([
        'HTTP_HOST' => 'localhost:8000',
        'SERVER_PORT' => 8000,
    ])
        ->get(route('login'))
        ->assertOk()
        ->assertSeeText('Microsoft sign-in is configured for http://127.0.0.1:8000/login. Open that URL or align APP_URL and MICROSOFT_REDIRECT_URI to the same origin.');
});

test('facebook host mismatch explains the required https login url', function () {
    config()->set('services.facebook.client_id', 'client-id');
    config()->set('services.facebook.client_secret', 'client-secret');
    config()->set('services.facebook.redirect', 'https://ysabelle-auth.ngrok-free.dev/auth/facebook/callback');

    $this->withServerVariables([
        'HTTP_HOST' => '127.0.0.1:8000',
        'SERVER_PORT' => 8000,
    ])
        ->get(route('login'))
        ->assertOk()
        ->assertSeeText('Facebook sign-in is configured for https://ysabelle-auth.ngrok-free.dev/login. Meta commonly requires an HTTPS callback for local testing, so use that HTTPS URL or align APP_URL and FACEBOOK_REDIRECT_URI to the same HTTPS origin.');
});
