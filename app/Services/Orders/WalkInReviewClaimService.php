<?php

namespace App\Services\Orders;

use App\Mail\Orders\WalkInReviewClaimMail;
use App\Models\Orders\Order;
use App\Models\Orders\OrderReviewClaim;
use App\Models\User;
use App\Services\Auth\CustomerAccountService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class WalkInReviewClaimService
{
    private const CLAIM_TTL_DAYS = 30;

    public function __construct(
        private readonly CustomerAccountService $customerAccounts,
    ) {}

    public function issueAndSendForEligibleOrder(Order $order): ?OrderReviewClaim
    {
        if (! $this->canIssueClaimFor($order)) {
            return null;
        }

        [$claim, $plainToken] = $this->issueForOrder($order);

        if (! $claim || ! $plainToken) {
            return $claim;
        }

        try {
            Mail::to($claim->customer_email)->send(new WalkInReviewClaimMail(
                claim: $claim->loadMissing('order.items'),
                claimUrl: route('storefront.account.review-claims.show', ['token' => $plainToken]),
            ));

            $claim->forceFill([
                'sent_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            report($exception);
        }

        return $claim->fresh(['order.items']);
    }

    public function findByPlainToken(string $plainToken): ?OrderReviewClaim
    {
        if ($plainToken === '' || ! preg_match('/^[a-f0-9]{64}$/', $plainToken)) {
            return null;
        }

        return OrderReviewClaim::query()
            ->with(['order.items', 'claimedBy'])
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();
    }

    public function claim(OrderReviewClaim $claim, User $user): Order
    {
        $this->assertClaimIsUsable($claim, $user);

        return DB::transaction(function () use ($claim, $user): Order {
            $order = $claim->order()->lockForUpdate()->firstOrFail();
            $freshClaim = $claim->newQuery()->lockForUpdate()->with('order')->findOrFail($claim->id);

            $this->assertClaimIsUsable($freshClaim, $user);
            $this->customerAccounts->ensureCustomerRole($user);

            $order->forceFill([
                'user_id' => $user->id,
                'customer_email' => Str::lower(trim((string) $user->email)),
            ])->save();

            $freshClaim->forceFill([
                'claimed_by_user_id' => $user->id,
                'used_at' => now(),
            ])->save();

            return $order->fresh(['items', 'reviewClaim']);
        });
    }

    public function statusFor(?OrderReviewClaim $claim, ?User $user): string
    {
        if (! $claim) {
            return 'invalid';
        }

        if ($claim->used_at || $claim->order->user_id) {
            return 'claimed';
        }

        if (! $this->canIssueClaimFor($claim->order)) {
            return 'unavailable';
        }

        if ($claim->expires_at->isPast()) {
            return 'expired';
        }

        if (! $user) {
            return 'guest';
        }

        if (Str::lower(trim((string) $user->email)) !== Str::lower(trim($claim->customer_email))) {
            return 'mismatch';
        }

        return 'ready';
    }

    public function maskedEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($domain === '') {
            return 'your matching email address';
        }

        $visibleLocal = Str::substr($local, 0, min(2, strlen($local)));

        return $visibleLocal.str_repeat('*', max(strlen($local) - strlen($visibleLocal), 1)).'@'.$domain;
    }

    private function issueForOrder(Order $order): array
    {
        $customerEmail = Str::lower(trim((string) $order->customer_email));
        $plainToken = bin2hex(random_bytes(32));

        $claim = OrderReviewClaim::query()->firstOrNew([
            'order_id' => $order->id,
        ]);

        if ($claim->exists && ($claim->used_at || $order->user_id)) {
            return [$claim->fresh(['order.items', 'claimedBy']), null];
        }

        $claim->forceFill([
            'claimed_by_user_id' => null,
            'customer_email' => $customerEmail,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(self::CLAIM_TTL_DAYS),
            'used_at' => null,
        ])->save();

        return [$claim->fresh(['order.items']), $plainToken];
    }

    private function canIssueClaimFor(Order $order): bool
    {
        return $order->source === 'walk_in'
            && filled($order->customer_email)
            && ! $order->user_id
            && $order->status === 'completed'
            && $order->payment_status === 'paid';
    }

    private function assertClaimIsUsable(OrderReviewClaim $claim, User $user): void
    {
        if (! $this->canIssueClaimFor($claim->order)) {
            throw ValidationException::withMessages([
                'claim' => 'This purchase is not eligible for a review claim.',
            ]);
        }

        if ($claim->used_at || $claim->order->user_id) {
            throw ValidationException::withMessages([
                'claim' => 'This purchase has already been claimed.',
            ]);
        }

        if ($claim->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'claim' => 'This claim link has expired.',
            ]);
        }

        if (Str::lower(trim((string) $user->email)) !== Str::lower(trim($claim->customer_email))) {
            throw ValidationException::withMessages([
                'claim' => 'Sign in with the same email address that received this claim link.',
            ]);
        }
    }
}
