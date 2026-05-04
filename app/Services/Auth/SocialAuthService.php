<?php

namespace App\Services\Auth;

use App\Models\Auth\SocialAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Contracts\User as ProviderUser;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

class SocialAuthService
{
    private const PROVIDERS = [
        'google' => [
            'name' => 'Google',
            'button_label' => 'Continue with Google',
            'scopes' => ['openid', 'profile', 'email'],
        ],
        'microsoft' => [
            'name' => 'Microsoft',
            'button_label' => 'Continue with Microsoft',
            'scopes' => ['openid', 'profile', 'User.Read'],
        ],
        'github' => [
            'name' => 'GitHub',
            'button_label' => 'Continue with GitHub',
            'scopes' => ['read:user', 'user:email'],
        ],
    ];

    public function __construct(
        private readonly SocialiteFactory $socialite,
        private readonly CustomerAccountService $customerAccounts,
        private readonly SocialProviderConfigurationValidator $providerConfiguration,
    ) {}

    public function providerButtons(?Request $request = null): array
    {
        return collect(self::PROVIDERS)
            ->map(function (array $providerMeta, string $provider) use ($request): array {
                $configuration = $this->providerConfiguration($provider, $request);

                return [
                    'key' => $provider,
                    'label' => $providerMeta['button_label'],
                    'configured' => $configuration->configured,
                    'available' => $configuration->available,
                    'status' => $configuration->message,
                    'href' => route('auth.social.redirect', ['provider' => $provider]),
                ];
            })
            ->values()
            ->all();
    }

    public function redirect(string $provider, ?Request $request = null): RedirectResponse
    {
        $configuration = $this->providerConfiguration($provider, $request);
        $this->ensureProviderIsAvailable($configuration);

        try {
            return $this->driver($provider)
                ->redirect();
        } catch (Throwable $exception) {
            throw $this->mapProviderException($provider, 'redirect', $exception, $configuration);
        }
    }

    public function resolveCallbackUser(string $provider, Request $request): User
    {
        $configuration = $this->providerConfiguration($provider);
        $this->ensureProviderIsConfigured($configuration);
        $this->throwIfProviderReturnedError($provider, $request, $configuration);

        try {
            $providerUser = $this->driver($provider)
                ->user();
        } catch (InvalidStateException $exception) {
            throw new SocialAuthException(
                'Your sign-in session expired or the callback host did not match the login page. Please start again from the same browser window.',
                context: [
                    'provider' => $provider,
                    'phase' => 'callback',
                    'exception' => InvalidStateException::class,
                    ...$configuration->context(),
                ],
                previous: $exception,
            );
        } catch (Throwable $exception) {
            throw $this->mapProviderException($provider, 'callback', $exception, $configuration);
        }

        return $this->resolveUser($provider, $providerUser);
    }

    public function ensureSupportedProvider(string $provider): void
    {
        if (Arr::exists(self::PROVIDERS, $provider)) {
            return;
        }

        abort(404);
    }

    public function isConfigured(string $provider): bool
    {
        return $this->providerConfiguration($provider)->configured;
    }

    private function providerConfiguration(
        string $provider,
        ?Request $request = null,
    ): SocialProviderConfigurationStatus {
        $this->ensureSupportedProvider($provider);

        return $this->providerConfiguration->validate(
            provider: $provider,
            providerName: $this->providerName($provider),
            request: $request,
        );
    }

    private function ensureProviderIsConfigured(
        SocialProviderConfigurationStatus $configuration,
    ): void {
        if ($configuration->configured) {
            return;
        }

        throw new SocialAuthException(
            $configuration->message ?? "{$configuration->providerName} sign-in is unavailable.",
            context: [
                'phase' => 'configuration',
                ...$configuration->context(),
            ],
        );
    }

    private function ensureProviderIsAvailable(
        SocialProviderConfigurationStatus $configuration,
    ): void {
        $this->ensureProviderIsConfigured($configuration);

        if ($configuration->available) {
            return;
        }

        throw new SocialAuthException(
            $configuration->message ?? "{$configuration->providerName} sign-in is unavailable.",
            context: [
                'phase' => 'configuration',
                ...$configuration->context(),
            ],
        );
    }

    private function driver(string $provider): mixed
    {
        $driver = $this->socialite->driver($provider)
            ->redirectUrl((string) config("services.{$provider}.redirect"))
            ->scopes(self::PROVIDERS[$provider]['scopes']);

        if ($parameters = $this->providerRedirectParameters($provider)) {
            $driver->with($parameters);
        }

        return $driver;
    }

    private function providerRedirectParameters(string $provider): array
    {
        return match ($provider) {
            'google', 'microsoft', 'github' => ['prompt' => 'select_account'],
            default => [],
        };
    }

    private function throwIfProviderReturnedError(
        string $provider,
        Request $request,
        SocialProviderConfigurationStatus $configuration,
    ): void {
        $error = trim((string) $request->query('error', ''));
        $errorDescription = trim((string) $request->query('error_description', $request->query('error_message', '')));
        $errorReason = trim((string) $request->query('error_reason', ''));

        if ($error === '' && $errorDescription === '' && $errorReason === '') {
            return;
        }

        throw new SocialAuthException(
            $this->providerErrorMessage($provider, $error, $errorDescription, $errorReason),
            context: [
                'provider' => $provider,
                'phase' => 'callback',
                'oauth_error' => $this->truncateDiagnostic($error),
                'oauth_error_reason' => $this->truncateDiagnostic($errorReason),
                'oauth_error_description' => $this->truncateDiagnostic($errorDescription),
                ...$configuration->context(),
            ],
        );
    }

    private function resolveUser(string $provider, ProviderUser $providerUser): User
    {
        $this->ensureSocialAccountsTableIsReady();

        $providerUserId = trim((string) $providerUser->getId());

        if ($providerUserId === '') {
            throw new SocialAuthException(
                'We could not verify your social account identity. Please try again.'
            );
        }

        $email = $this->resolveProviderEmail($provider, $providerUser, $providerUserId);
        $name = $this->resolveDisplayName($providerUser, $email);
        $avatar = $providerUser->getAvatar();

        if ($provider === 'github') {
            $linkedUser = User::query()
                ->where('github_id', $providerUserId)
                ->first();

            if ($linkedUser) {
                $this->syncGithubIdentity($linkedUser, $providerUserId);
                $this->syncSocialAccount($linkedUser, $provider, $providerUserId, $email, $avatar);

                return $this->ensureUserIsActive($linkedUser);
            }
        }

        $linkedAccount = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first();

        if ($linkedAccount) {
            $user = $this->ensureUserIsActive($linkedAccount->user);

            if ($provider === 'github') {
                $this->syncGithubIdentity($user, $providerUserId);
            }

            $this->syncSocialAccount($user, $provider, $providerUserId, $email, $avatar);

            return $user;
        }

        $user = User::query()
            ->where('email', $email)
            ->first();

        $user ??= $this->customerAccounts->registerFromSocial(
            name: $name,
            email: $email,
            markEmailVerified: $this->providerEmailIsTrusted($provider, $providerUser),
        );

        $user = $this->ensureUserIsActive($user);

        if ($provider === 'github') {
            $this->syncGithubIdentity($user, $providerUserId);
        }

        $this->syncSocialAccount($user, $provider, $providerUserId, $email, $avatar);

        return $user;
    }

    private function syncGithubIdentity(User $user, string $providerUserId): void
    {
        $user->forceFill([
            'github_id' => $providerUserId,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ]);

        if ($user->isDirty(['github_id', 'email_verified_at'])) {
            $user->save();
        }
    }

    private function ensureUserIsActive(User $user): User
    {
        if ($user->isActive()) {
            return $user;
        }

        throw new SocialAuthException(
            'This account is inactive. Please contact an administrator.'
        );
    }

    private function providerEmailIsTrusted(
        string $provider,
        ProviderUser $providerUser,
    ): bool {
        if (in_array($provider, ['google', 'microsoft', 'github'], true)) {
            return true;
        }

        return collect([
            data_get($providerUser, 'user.email_verified'),
            data_get($providerUser, 'user.verified_email'),
            data_get($providerUser, 'user.is_verified'),
        ])->contains(fn (mixed $value): bool => filter_var($value, FILTER_VALIDATE_BOOL));
    }

    private function resolveDisplayName(
        ProviderUser $providerUser,
        ?string $email,
    ): string {
        $name = trim((string) ($providerUser->getName() ?: $providerUser->getNickname()));

        if ($name !== '') {
            return Str::limit($name, 120, '');
        }

        if ($email) {
            return Str::limit((string) Str::of($email)->before('@')->headline(), 120, '');
        }

        return 'Ysabelle Shopper';
    }

    private function normalizeEmail(?string $email): ?string
    {
        if (! is_string($email)) {
            return null;
        }

        $normalized = Str::lower(trim($email));

        return $normalized === '' ? null : $normalized;
    }

    private function resolveProviderEmail(
        string $provider,
        ProviderUser $providerUser,
        string $providerUserId,
    ): string {
        $email = $this->normalizeEmail($providerUser->getEmail());

        if ($email) {
            return $email;
        }

        if ($provider === 'github') {
            $nickname = Str::lower(trim((string) $providerUser->getNickname()));
            $localPart = $nickname !== '' ? $nickname : 'github-user-'.$providerUserId;

            return "{$localPart}@github.local";
        }

        throw new SocialAuthException(
            'We could not read an email address from this provider. Please sign in with email and password instead.'
        );
    }

    private function providerName(string $provider): string
    {
        return self::PROVIDERS[$provider]['name'];
    }

    private function providerErrorMessage(
        string $provider,
        string $error,
        string $errorDescription,
        string $errorReason,
    ): string {
        $providerName = $this->providerName($provider);
        $diagnostic = Str::lower(trim(implode(' ', array_filter([
            $error,
            $errorReason,
            $errorDescription,
        ]))));

        if (
            str_contains($diagnostic, 'access_denied')
            || str_contains($diagnostic, 'permissions')
            || str_contains($diagnostic, 'user_denied')
        ) {
            if (in_array($provider, ['microsoft', 'github'], true)) {
                return "{$providerName} sign-in was cancelled. Please try again if you want to continue.";
            }

            return "{$providerName} sign-in was cancelled or the requested permissions were denied.";
        }

        if (
            str_contains($diagnostic, 'redirect_uri')
            || str_contains($diagnostic, 'aadsts50011')
        ) {
            return "{$providerName} sign-in is temporarily unavailable because the callback URL does not match the provider configuration.";
        }

        if (
            str_contains($diagnostic, 'invalid_client')
            || str_contains($diagnostic, 'unauthorized_client')
            || str_contains($diagnostic, 'client secret')
            || str_contains($diagnostic, 'client id')
        ) {
            return "{$providerName} sign-in is temporarily unavailable because the provider credentials were rejected.";
        }

        return "{$providerName} sign-in could not be completed. Please try again or use email and password.";
    }

    private function syncSocialAccount(
        User $user,
        string $provider,
        string $providerUserId,
        string $email,
        ?string $avatar,
    ): void {
        $user->socialAccounts()->updateOrCreate(
            ['provider' => $provider],
            [
                'provider_user_id' => $providerUserId,
                'provider_email' => $email,
                'avatar' => $avatar ? Str::limit($avatar, 255, '') : null,
            ],
        );
    }

    private function ensureSocialAccountsTableIsReady(): void
    {
        if (Schema::hasTable('social_accounts')) {
            return;
        }

        throw new SocialAuthException(
            'Social login is not ready yet because the social_accounts table is missing. Please run php artisan migrate and try again.',
            context: [
                'phase' => 'database',
                'missing_table' => 'social_accounts',
            ],
            reportLevel: 'error',
        );
    }

    private function mapProviderException(
        string $provider,
        string $phase,
        Throwable $exception,
        SocialProviderConfigurationStatus $configuration,
    ): SocialAuthException {
        $payload = $this->exceptionPayload($exception);

        return new SocialAuthException(
            $this->providerErrorMessage($provider, $exception->getMessage(), $payload, ''),
            context: [
                'provider' => $provider,
                'phase' => $phase,
                'exception' => $exception::class,
                'oauth_error_description' => $this->truncateDiagnostic($payload),
                ...$configuration->context(),
            ],
            previous: $exception,
        );
    }

    private function exceptionPayload(Throwable $exception): string
    {
        if (! method_exists($exception, 'hasResponse') || ! $exception->hasResponse()) {
            return '';
        }

        return trim((string) $exception->getResponse()->getBody());
    }

    private function truncateDiagnostic(string $value): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', $value) ?? ''), 300, '');
    }
}
