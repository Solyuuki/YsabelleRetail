<?php

namespace App\Services\Storefront\Assistant;

interface ShoppingAssistantGuidanceProvider
{
    public function isEnabled(): bool;

    public function complete(string $intent, string $userMessage, array $response, array $context): ?string;

    public function health(): array;
}
