<?php

namespace App\Services\Auth;

use Illuminate\Http\Request;

class SocialProviderConfigurationValidator
{
    public function validate(
        string $provider,
        string $providerName,
        ?Request $request = null,
    ): SocialProviderConfigurationStatus {
        $config = config("services.{$provider}", []);
        $redirectUri = trim((string) data_get($config, 'redirect'));
        $expectedCallbackPath = $this->callbackPath($provider);
        $missingKeys = collect(['client_id', 'client_secret', 'redirect'])
            ->filter(fn (string $key): bool => blank(data_get($config, $key)))
            ->values()
            ->all();

        if ($missingKeys !== []) {
            return new SocialProviderConfigurationStatus(
                provider: $provider,
                providerName: $providerName,
                configured: false,
                available: false,
                message: "{$providerName} sign-in is not configured yet. Please use email and password for now.",
                issues: ['missing_credentials'],
                expectedCallbackPath: $expectedCallbackPath,
            );
        }

        if (! filter_var($redirectUri, FILTER_VALIDATE_URL)) {
            return new SocialProviderConfigurationStatus(
                provider: $provider,
                providerName: $providerName,
                configured: true,
                available: false,
                message: "{$providerName} sign-in is unavailable because the callback URL is invalid.",
                issues: ['invalid_redirect_uri'],
                redirectUri: $redirectUri,
                expectedCallbackPath: $expectedCallbackPath,
            );
        }

        $redirectPath = parse_url($redirectUri, PHP_URL_PATH) ?: null;
        $expectedOrigin = $this->originFromUrl($redirectUri);

        if ($redirectPath !== $expectedCallbackPath) {
            return new SocialProviderConfigurationStatus(
                provider: $provider,
                providerName: $providerName,
                configured: true,
                available: false,
                message: "{$providerName} sign-in is unavailable because the callback route does not match the provider configuration.",
                issues: ['callback_path_mismatch'],
                redirectUri: $redirectUri,
                expectedCallbackPath: $expectedCallbackPath,
                expectedOrigin: $expectedOrigin,
            );
        }

        $currentOrigin = $request ? $this->originFromRequest($request) : null;

        if ($request && $expectedOrigin !== $currentOrigin) {
            $loginUrl = rtrim($expectedOrigin, '/').route('login', absolute: false);
            $message = "{$providerName} sign-in is available from {$loginUrl}. Open that URL so the callback host matches this provider configuration.";

            if ($provider === 'microsoft') {
                $message = "Microsoft sign-in is available from {$loginUrl} because the local Microsoft callback is registered for localhost.";
            }

            return new SocialProviderConfigurationStatus(
                provider: $provider,
                providerName: $providerName,
                configured: true,
                available: false,
                message: $message,
                issues: ['callback_host_mismatch'],
                redirectUri: $redirectUri,
                expectedCallbackPath: $expectedCallbackPath,
                expectedOrigin: $expectedOrigin,
                currentOrigin: $currentOrigin,
            );
        }

        return new SocialProviderConfigurationStatus(
            provider: $provider,
            providerName: $providerName,
            configured: true,
            available: true,
            redirectUri: $redirectUri,
            expectedCallbackPath: $expectedCallbackPath,
            expectedOrigin: $expectedOrigin,
            currentOrigin: $currentOrigin,
        );
    }

    private function callbackPath(string $provider): string
    {
        return (string) parse_url(
            route('auth.social.callback', ['provider' => $provider], false),
            PHP_URL_PATH,
        );
    }

    private function originFromRequest(Request $request): string
    {
        return $this->formatOrigin(
            $request->getScheme(),
            (string) $request->getHost(),
            $request->getPort(),
        );
    }

    private function originFromUrl(string $url): string
    {
        return $this->formatOrigin(
            (string) parse_url($url, PHP_URL_SCHEME),
            (string) parse_url($url, PHP_URL_HOST),
            $this->normalizedPort(
                (string) parse_url($url, PHP_URL_SCHEME),
                parse_url($url, PHP_URL_PORT),
            ),
        );
    }

    private function formatOrigin(string $scheme, string $host, int $port): string
    {
        $portSuffix = $port === $this->defaultPort($scheme) ? '' : ":{$port}";

        return "{$scheme}://{$host}{$portSuffix}";
    }

    private function normalizedPort(string $scheme, int|string|false|null $port): int
    {
        if (is_int($port)) {
            return $port;
        }

        return $this->defaultPort($scheme);
    }

    private function defaultPort(string $scheme): int
    {
        return $scheme === 'https' ? 443 : 80;
    }
}
