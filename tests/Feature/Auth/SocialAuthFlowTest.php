<?php

use App\Models\Access\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

function configureSocialProvider(string $provider): void
{
    $appUrl = (string) config('app.url');
    config()->set("services.{$provider}.client_id", 'client-id');
    config()->set("services.{$provider}.client_secret", 'client-secret');
    config()->set("services.{$provider}.redirect", rtrim($appUrl, '/')."/auth/{$provider}/callback");
}

function bindSocialiteDriver(string $provider, array $expectations): void
{
    $driver = \Mockery::mock();
    $driver->shouldReceive('redirectUrl')
        ->zeroOrMoreTimes()
        ->andReturnSelf();
    $driver->shouldReceive('scopes')
        ->zeroOrMoreTimes()
        ->andReturnSelf();
    $driver->shouldReceive('with')
        ->zeroOrMoreTimes()
        ->andReturnSelf();

    if ($provider === 'facebook') {
        $driver->shouldReceive('fields')
            ->zeroOrMoreTimes()
            ->andReturnSelf();
    }

    foreach ($expectations as $method => $returnValue) {
        $expectation = $driver->shouldReceive($method)
            ->once();

        if ($returnValue instanceof \Throwable) {
            $expectation->andThrow($returnValue);

            continue;
        }

        $expectation->andReturn($returnValue);
    }

    $factory = \Mockery::mock(SocialiteFactory::class);
    $factory->shouldReceive('driver')
        ->once()
        ->with($provider)
        ->andReturn($driver);

    app()->instance(SocialiteFactory::class, $factory);
}

function createSocialiteUser(array $attributes = []): SocialiteUser
{
    $user = new SocialiteUser();
    $user->id = $attributes['id'] ?? 'provider-user-1';
    $user->nickname = $attributes['nickname'] ?? null;
    $user->name = $attributes['name'] ?? 'Ysabelle Shopper';
    $user->email = $attributes['email'] ?? 'social@example.com';
    $user->avatar = $attributes['avatar'] ?? 'https://example.test/avatar.jpg';
    $user->user = $attributes['user'] ?? ['email_verified' => true];

    return $user;
}

function ensureSocialCustomerRoleExists(): void
{
    Role::query()->firstOrCreate(
        ['slug' => 'customer'],
        [
            'name' => 'Customer',
            'description' => 'Customer role',
            'is_system' => true,
        ],
    );
}

test('configured social providers redirect to the provider handshake', function () {
    configureSocialProvider('google');

    bindSocialiteDriver('google', [
        'redirect' => new RedirectResponse('https://accounts.google.com/o/oauth2/auth'),
    ]);

    $this->get(route('auth.social.redirect', ['provider' => 'google']))
        ->assertRedirect('https://accounts.google.com/o/oauth2/auth');
});

test('microsoft socialite driver is registered', function () {
    configureSocialProvider('microsoft');

    expect(get_class(app(SocialiteFactory::class)->driver('microsoft')))
        ->toBe(\SocialiteProviders\Microsoft\Provider::class);
});

test('missing provider credentials return an honest error response', function () {
    config()->set('services.google', [
        'client_id' => null,
        'client_secret' => null,
        'redirect' => null,
    ]);

    $this->from(route('login'))
        ->get(route('auth.social.redirect', ['provider' => 'google']))
        ->assertRedirect(route('login'))
        ->assertSessionHas('toast.message', 'Google sign-in is not configured yet. Please use email and password for now.');

    $this->assertGuest();
});

test('unsupported social providers are rejected', function () {
    $this->get('/auth/apple/redirect')->assertNotFound();
});

test('provider redirect is blocked when the callback host does not match the current host', function () {
    configureSocialProvider('google');
    config()->set('services.google.redirect', 'http://127.0.0.1:8000/auth/google/callback');

    $this->withServerVariables([
        'HTTP_HOST' => 'localhost:8000',
        'SERVER_PORT' => 8000,
    ])
        ->get(route('auth.social.redirect', ['provider' => 'google']))
        ->assertRedirect(route('login'))
        ->assertSessionHas(
            'toast.message',
            'Google sign-in is configured for http://127.0.0.1:8000/login. Open that URL or align APP_URL and GOOGLE_REDIRECT_URI to the same origin.'
        );
});

test('google redirect requests account selection when possible', function () {
    configureSocialProvider('google');

    $driver = \Mockery::mock();
    $driver->shouldReceive('redirectUrl')
        ->once()
        ->andReturnSelf();
    $driver->shouldReceive('scopes')
        ->once()
        ->andReturnSelf();
    $driver->shouldReceive('with')
        ->once()
        ->with(['prompt' => 'select_account'])
        ->andReturnSelf();
    $driver->shouldReceive('redirect')
        ->once()
        ->andReturn(new RedirectResponse('https://accounts.google.com/o/oauth2/auth?prompt=select_account'));

    $factory = \Mockery::mock(SocialiteFactory::class);
    $factory->shouldReceive('driver')
        ->once()
        ->with('google')
        ->andReturn($driver);

    app()->instance(SocialiteFactory::class, $factory);

    $this->get(route('auth.social.redirect', ['provider' => 'google']))
        ->assertRedirect('https://accounts.google.com/o/oauth2/auth?prompt=select_account');
});

test('configured social callbacks create and authenticate the shopper', function () {
    ensureSocialCustomerRoleExists();
    configureSocialProvider('google');

    bindSocialiteDriver('google', [
        'user' => createSocialiteUser([
            'id' => 'google-123',
            'name' => 'Google Shopper',
            'email' => 'google-shopper@example.com',
        ]),
    ]);

    $this->get(route('auth.social.callback', ['provider' => 'google']))
        ->assertRedirect(route('storefront.account.index'));

    $user = User::query()->where('email', 'google-shopper@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user?->hasRole('customer'))->toBeTrue();
    expect($user?->socialAccounts()->where('provider', 'google')->exists())->toBeTrue();

    $this->assertAuthenticatedAs($user);
});

test('facebook callback with app inactive error returns a clear message', function () {
    configureSocialProvider('facebook');

    $this->get(route('auth.social.callback', [
        'provider' => 'facebook',
        'error' => 'temporarily_unavailable',
        'error_description' => 'App not active',
    ]))
        ->assertRedirect(route('login'))
        ->assertSessionHas(
            'toast.message',
            'Facebook login is currently unavailable because the Meta app is not active or this account is not assigned as an app tester/developer.'
        );
});

test('provider callback failures are translated into safe redirect mismatch messages', function () {
    configureSocialProvider('google');

    bindSocialiteDriver('google', [
        'user' => new \RuntimeException('redirect_uri_mismatch'),
    ]);

    $this->get(route('auth.social.callback', ['provider' => 'google']))
        ->assertRedirect(route('login'))
        ->assertSessionHas(
            'toast.message',
            'Google sign-in is temporarily unavailable because the callback URL does not match the provider configuration.'
        );
});

test('existing accounts are not auto-linked for untrusted facebook emails', function () {
    ensureSocialCustomerRoleExists();
    configureSocialProvider('facebook');

    User::factory()->create([
        'email' => 'existing-shopper@example.com',
        'status' => 'active',
    ]);

    bindSocialiteDriver('facebook', [
        'user' => createSocialiteUser([
            'id' => 'facebook-123',
            'name' => 'Existing Shopper',
            'email' => 'existing-shopper@example.com',
            'user' => ['email_verified' => false],
        ]),
    ]);

    $this->get(route('auth.social.callback', ['provider' => 'facebook']))
        ->assertRedirect(route('login'))
        ->assertSessionHas(
            'toast.message',
            'We could not safely link this social account. Please sign in with your password first.'
        );
});

test('social callback fails gracefully when social accounts table is missing', function () {
    ensureSocialCustomerRoleExists();
    configureSocialProvider('google');

    \Illuminate\Support\Facades\Schema::dropIfExists('social_accounts');

    bindSocialiteDriver('google', [
        'user' => createSocialiteUser([
            'id' => 'google-123',
            'name' => 'Google Shopper',
            'email' => 'google-shopper@example.com',
        ]),
    ]);

    $this->get(route('auth.social.callback', ['provider' => 'google']))
        ->assertRedirect(route('login'))
        ->assertSessionHas(
            'toast.message',
            'Social login is not ready yet because the social_accounts table is missing. Please run php artisan migrate and try again.'
        );
});
