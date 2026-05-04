<?php

namespace App\Services\Auth;

class SocialProviderConfigurationStatus
{
    public function __construct(
        public readonly string $provider,
        public readonly string $providerName,
        public readonly bool $configured,
        public readonly bool $available,
        public readonly ?string $message = null,
        public readonly array $issues = [],
        public readonly ?string $redirectUri = null,
        public readonly ?string $expectedCallbackPath = null,
        public readonly ?string $expectedOrigin = null,
        public readonly ?string $currentOrigin = null,
    ) {}

    public function context(): array
    {
        return array_filter([
            'provider' => $this->provider,
            'provider_name' => $this->providerName,
            'configured' => $this->configured,
            'available' => $this->available,
            'issues' => $this->issues,
            'redirect_uri' => $this->redirectUri,
            'expected_callback_path' => $this->expectedCallbackPath,
            'expected_origin' => $this->expectedOrigin,
            'current_origin' => $this->currentOrigin,
        ], fn (mixed $value): bool => $value !== null && $value !== []);
    }
}
