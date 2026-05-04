<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('login page renders the redesigned auth experience', function () {
    foreach (['google', 'microsoft', 'github'] as $provider) {
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
        ->assertSeeText('Continue with GitHub')
        ->assertSeeText('Google sign-in is not configured yet. Please use email and password for now.')
        ->assertSee('href="'.route('storefront.terms').'"', escape: false)
        ->assertSee('href="'.route('storefront.privacy').'"', escape: false)
        ->assertSeeText('Terms of Use')
        ->assertSeeText('Privacy Policy');
});

test('register page renders the redesigned auth experience', function () {
    foreach (['google', 'microsoft', 'github'] as $provider) {
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
        ->assertSeeText('Continue with GitHub')
        ->assertSeeText('Google sign-in is not configured yet. Please use email and password for now.')
        ->assertSee('href="'.route('storefront.terms').'"', escape: false)
        ->assertSee('href="'.route('storefront.privacy').'"', escape: false)
        ->assertSeeText('Terms of Use')
        ->assertSeeText('Privacy Policy');
});

test('configured providers render a real oauth redirect link', function () {
    $appUrl = (string) config('app.url');
    config()->set('services.google.client_id', 'client-id');
    config()->set('services.google.client_secret', 'client-secret');
    config()->set('services.google.redirect', rtrim($appUrl, '/').'/auth/google/callback');
    config()->set('services.github.client_id', 'client-id');
    config()->set('services.github.client_secret', 'client-secret');
    config()->set('services.github.redirect', rtrim($appUrl, '/').'/auth/github/callback');

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('href="'.rtrim($appUrl, '/').'/auth/google/redirect"', escape: false)
        ->assertSee('href="'.rtrim($appUrl, '/').'/auth/github/redirect"', escape: false);
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

test('github host mismatch explains the exact configured login url', function () {
    config()->set('services.github.client_id', 'client-id');
    config()->set('services.github.client_secret', 'client-secret');
    config()->set('services.github.redirect', 'http://127.0.0.1:8000/auth/github/callback');

    $this->withServerVariables([
        'HTTP_HOST' => 'localhost:8000',
        'SERVER_PORT' => 8000,
    ])
        ->get(route('login'))
        ->assertOk()
        ->assertSeeText('GitHub sign-in is configured for http://127.0.0.1:8000/login. Open that URL or align APP_URL and GITHUB_REDIRECT_URI to the same origin.');
});
