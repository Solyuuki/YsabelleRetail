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
        ->assertSeeText('Google sign-in is available from http://127.0.0.1:8000/login. Open that URL so the callback host matches this provider configuration.');
});

test('microsoft host mismatch explains the exact local login url', function () {
    config()->set('services.microsoft.client_id', 'client-id');
    config()->set('services.microsoft.client_secret', 'client-secret');
    config()->set('services.microsoft.redirect', 'http://localhost:8000/auth/microsoft/callback');

    $this->withServerVariables([
        'HTTP_HOST' => '127.0.0.1:8000',
        'SERVER_PORT' => 8000,
    ])
        ->get(route('login'))
        ->assertOk()
        ->assertSeeText('Microsoft sign-in is available from http://localhost:8000/login because the local Microsoft callback is registered for localhost.');
});
