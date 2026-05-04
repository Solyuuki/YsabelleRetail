<?php

use App\Models\Access\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Two\GithubProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use SocialiteProviders\Microsoft\Provider;

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
    $driver = Mockery::mock();
    $driver->shouldReceive('redirectUrl')
        ->zeroOrMoreTimes()
        ->andReturnSelf();
    $driver->shouldReceive('scopes')
        ->zeroOrMoreTimes()
        ->andReturnSelf();
    $driver->shouldReceive('with')
        ->zeroOrMoreTimes()
        ->andReturnSelf();

    foreach ($expectations as $method => $returnValue) {
        $expectation = $driver->shouldReceive($method)
            ->once();

        if ($returnValue instanceof Throwable) {
            $expectation->andThrow($returnValue);

            continue;
        }

        $expectation->andReturn($returnValue);
    }

    $factory = Mockery::mock(SocialiteFactory::class);
    $factory->shouldReceive('driver')
        ->once()
        ->with($provider)
        ->andReturn($driver);

    app()->instance(SocialiteFactory::class, $factory);
}

function createSocialiteUser(array $attributes = []): SocialiteUser
{
    $user = new SocialiteUser;
    $user->id = $attributes['id'] ?? 'provider-user-1';
    $user->nickname = $attributes['nickname'] ?? null;
    $user->name = $attributes['name'] ?? 'Ysabelle Shopper';
    $user->email = array_key_exists('email', $attributes) ? $attributes['email'] : 'social@example.com';
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
        ->toBe(Provider::class);
});

test('github socialite driver is registered', function () {
    configureSocialProvider('github');

    expect(get_class(app(SocialiteFactory::class)->driver('github')))
        ->toBe(GithubProvider::class);
});

test('github oauth routes resolve to the expected callback flow paths', function () {
    expect(route('auth.social.redirect', ['provider' => 'github'], false))
        ->toBe('/auth/github/redirect');

    expect(route('auth.social.callback', ['provider' => 'github'], false))
        ->toBe('/auth/github/callback');
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

    $driver = Mockery::mock();
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

    $factory = Mockery::mock(SocialiteFactory::class);
    $factory->shouldReceive('driver')
        ->once()
        ->with('google')
        ->andReturn($driver);

    app()->instance(SocialiteFactory::class, $factory);

    $this->get(route('auth.social.redirect', ['provider' => 'google']))
        ->assertRedirect('https://accounts.google.com/o/oauth2/auth?prompt=select_account');
});

test('microsoft redirect requests account selection when possible', function () {
    configureSocialProvider('microsoft');

    $driver = Mockery::mock();
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
        ->andReturn(new RedirectResponse('https://login.microsoftonline.com/common/oauth2/v2.0/authorize?prompt=select_account'));

    $factory = Mockery::mock(SocialiteFactory::class);
    $factory->shouldReceive('driver')
        ->once()
        ->with('microsoft')
        ->andReturn($driver);

    app()->instance(SocialiteFactory::class, $factory);

    $this->get(route('auth.social.redirect', ['provider' => 'microsoft']))
        ->assertRedirect('https://login.microsoftonline.com/common/oauth2/v2.0/authorize?prompt=select_account');
});

test('github redirect requests account selection when possible', function () {
    configureSocialProvider('github');

    $driver = Mockery::mock();
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
        ->andReturn(new RedirectResponse('https://github.com/login/oauth/authorize?prompt=select_account'));

    $factory = Mockery::mock(SocialiteFactory::class);
    $factory->shouldReceive('driver')
        ->once()
        ->with('github')
        ->andReturn($driver);

    app()->instance(SocialiteFactory::class, $factory);

    $this->get(route('auth.social.redirect', ['provider' => 'github']))
        ->assertRedirect('https://github.com/login/oauth/authorize?prompt=select_account');
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

test('configured microsoft callback creates and authenticates the shopper', function () {
    ensureSocialCustomerRoleExists();
    configureSocialProvider('microsoft');

    bindSocialiteDriver('microsoft', [
        'user' => createSocialiteUser([
            'id' => 'microsoft-123',
            'name' => 'Microsoft Shopper',
            'email' => 'microsoft-shopper@example.com',
        ]),
    ]);

    $this->get(route('auth.social.callback', ['provider' => 'microsoft']))
        ->assertRedirect(route('storefront.account.index'));

    $user = User::query()->where('email', 'microsoft-shopper@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user?->hasRole('customer'))->toBeTrue();
    expect($user?->socialAccounts()->where('provider', 'microsoft')->exists())->toBeTrue();

    $this->assertAuthenticatedAs($user);
});

test('provider callback failures are translated into safe redirect mismatch messages', function () {
    configureSocialProvider('google');

    bindSocialiteDriver('google', [
        'user' => new RuntimeException('redirect_uri_mismatch'),
    ]);

    $this->get(route('auth.social.callback', ['provider' => 'google']))
        ->assertRedirect(route('login'))
        ->assertSessionHas(
            'toast.message',
            'Google sign-in is temporarily unavailable because the callback URL does not match the provider configuration.'
        );
});

test('microsoft callback cancellation redirects safely without authenticating the user', function () {
    configureSocialProvider('microsoft');

    $factory = Mockery::mock(SocialiteFactory::class);
    $factory->shouldNotReceive('driver');
    app()->instance(SocialiteFactory::class, $factory);

    $this->get(route('auth.social.callback', [
        'provider' => 'microsoft',
        'error' => 'access_denied',
        'error_description' => 'The user cancelled the authentication flow.',
    ]))
        ->assertRedirect(route('login'))
        ->assertSessionHas(
            'toast.message',
            'Microsoft sign-in was cancelled. Please try again if you want to continue.'
        );

    expect(User::query()->count())->toBe(0);
    $this->assertGuest();
});

test('github callback cancellation redirects safely without authenticating the user', function () {
    configureSocialProvider('github');

    $factory = Mockery::mock(SocialiteFactory::class);
    $factory->shouldNotReceive('driver');
    app()->instance(SocialiteFactory::class, $factory);

    $this->get(route('auth.social.callback', [
        'provider' => 'github',
        'error' => 'access_denied',
        'error_description' => 'The user cancelled the authentication flow.',
    ]))
        ->assertRedirect(route('login'))
        ->assertSessionHas(
            'toast.message',
            'GitHub sign-in was cancelled. Please try again if you want to continue.'
        );

    expect(User::query()->count())->toBe(0);
    $this->assertGuest();
});

test('existing accounts are linked and authenticated when github email already exists', function () {
    ensureSocialCustomerRoleExists();
    configureSocialProvider('github');

    $existingUser = User::factory()->create([
        'email' => 'existing-shopper@example.com',
        'status' => 'active',
    ]);

    bindSocialiteDriver('github', [
        'user' => createSocialiteUser([
            'id' => 'github-123',
            'name' => 'Existing Shopper',
            'email' => 'existing-shopper@example.com',
        ]),
    ]);

    $this->get(route('auth.social.callback', ['provider' => 'github']))
        ->assertRedirect(route('storefront.account.index'));

    $existingUser->refresh();

    expect($existingUser->github_id)->toBe('github-123');
    expect($existingUser->socialAccounts()->where('provider', 'github')->exists())->toBeTrue();
    $this->assertAuthenticatedAs($existingUser);
});

test('github callback creates a fallback email when github does not provide one', function () {
    ensureSocialCustomerRoleExists();
    configureSocialProvider('github');

    bindSocialiteDriver('github', [
        'user' => createSocialiteUser([
            'id' => 'github-456',
            'nickname' => 'octocat',
            'name' => 'Octo Cat',
            'email' => null,
        ]),
    ]);

    $this->get(route('auth.social.callback', ['provider' => 'github']))
        ->assertRedirect(route('storefront.account.index'));

    $user = User::query()->where('email', 'octocat@github.local')->first();

    expect($user)->not->toBeNull();
    expect($user?->github_id)->toBe('github-456');
    expect($user?->email_verified_at)->not->toBeNull();
    $this->assertAuthenticatedAs($user);
});

test('github callback backfills github id for an existing linked social account and authenticates the user', function () {
    ensureSocialCustomerRoleExists();
    configureSocialProvider('github');

    $existingUser = User::factory()->create([
        'email' => 'linked-shopper@example.com',
        'github_id' => null,
        'status' => 'active',
    ]);

    $existingUser->socialAccounts()->create([
        'provider' => 'github',
        'provider_user_id' => 'github-789',
        'provider_email' => 'linked-shopper@example.com',
        'avatar' => 'https://example.test/old-avatar.jpg',
    ]);

    bindSocialiteDriver('github', [
        'user' => createSocialiteUser([
            'id' => 'github-789',
            'name' => 'Linked Shopper',
            'email' => 'linked-shopper@example.com',
            'avatar' => 'https://example.test/new-avatar.jpg',
        ]),
    ]);

    $this->get(route('auth.social.callback', ['provider' => 'github']))
        ->assertRedirect(route('storefront.account.index'));

    $existingUser->refresh();

    expect($existingUser->github_id)->toBe('github-789');
    expect($existingUser->email_verified_at)->not->toBeNull();
    expect($existingUser->socialAccounts()->where('provider', 'github')->value('avatar'))
        ->toBe('https://example.test/new-avatar.jpg');
    $this->assertAuthenticatedAs($existingUser);
});

test('social callback fails gracefully when social accounts table is missing', function () {
    ensureSocialCustomerRoleExists();
    configureSocialProvider('google');

    Schema::dropIfExists('social_accounts');

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
