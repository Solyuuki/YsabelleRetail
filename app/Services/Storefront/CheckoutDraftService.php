<?php

namespace App\Services\Storefront;

use App\Models\CheckoutDraft;
use App\Models\User;

class CheckoutDraftService
{
    private const SAFE_DRAFT_KEYS = [
        'full_name',
        'email',
        'phone',
        'city',
        'address',
        'postal_code',
        'order_notes',
        'payment_method',
    ];

    private const TTL_DAYS = 7;

    public function dataFor(User $user): ?array
    {
        $draft = $this->activeDraftFor($user);

        return $draft?->payload;
    }

    public function save(User $user, array $payload): ?CheckoutDraft
    {
        $draftPayload = $this->draftPayload($payload);

        if ($this->isBlankDraft($draftPayload)) {
            $this->clear($user);

            return null;
        }

        return CheckoutDraft::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'payload' => $draftPayload,
                'expires_at' => now()->addDays(self::TTL_DAYS),
            ],
        );
    }

    public function clear(User $user): void
    {
        CheckoutDraft::query()
            ->where('user_id', $user->id)
            ->delete();
    }

    public function expiresInDays(): int
    {
        return self::TTL_DAYS;
    }

    private function activeDraftFor(User $user): ?CheckoutDraft
    {
        $draft = CheckoutDraft::query()
            ->where('user_id', $user->id)
            ->first();

        if (! $draft) {
            return null;
        }

        if ($draft->expires_at?->isPast()) {
            $draft->delete();

            return null;
        }

        return $draft;
    }

    private function draftPayload(array $payload): array
    {
        $draftPayload = [];

        foreach (self::SAFE_DRAFT_KEYS as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }

            $draftPayload[$key] = $payload[$key];
        }

        return $draftPayload;
    }

    private function isBlankDraft(array $payload): bool
    {
        foreach ($payload as $value) {
            if (is_string($value) && trim($value) !== '') {
                return false;
            }

            if (! is_string($value) && ! is_null($value)) {
                return false;
            }
        }

        return true;
    }
}
