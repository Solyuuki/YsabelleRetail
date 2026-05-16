<?php

use App\Models\Access\Role;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductReview;
use App\Models\Catalog\ProductVariant;
use App\Models\Orders\Order;
use App\Models\Orders\OrderReviewClaim;
use App\Models\User;
use App\Mail\Orders\WalkInReviewClaimMail;
use App\Services\Orders\WalkInReviewClaimService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function walkInReviewClaimCustomer(array $attributes = []): User
{
    $role = Role::query()->firstOrCreate(
        ['slug' => 'customer'],
        ['name' => 'Customer', 'description' => 'Customer role', 'is_system' => true],
    );

    $user = User::factory()->create($attributes);
    $user->roles()->attach($role);

    return $user;
}

function walkInReviewClaimAdmin(array $attributes = []): User
{
    $role = Role::query()->firstOrCreate(
        ['slug' => 'admin'],
        ['name' => 'Admin', 'description' => 'Admin role', 'is_system' => true],
    );

    $user = User::factory()->create($attributes);
    $user->roles()->attach($role);

    return $user;
}

function walkInReviewClaimProduct(array $overrides = []): Product
{
    $category = Category::factory()->create([
        'name' => 'Walk-In Reviews '.random_int(100, 999),
        'slug' => 'walk-in-reviews-'.random_int(1000, 9999),
        'is_active' => true,
    ]);

    $product = Product::factory()->for($category)->create(array_merge([
        'name' => 'Claimable Runner '.random_int(100, 999),
        'slug' => 'claimable-runner-'.random_int(1000, 9999),
        'status' => 'active',
        'base_price' => 5290,
        'compare_at_price' => 5990,
    ], $overrides));

    $variant = ProductVariant::factory()->for($product)->create([
        'name' => 'Size 9',
        'sku' => 'YS-CLAIM-'.random_int(1000, 9999),
        'option_values' => ['size' => '9', 'color' => 'Black/Gold'],
        'status' => 'active',
        'price' => 5290,
    ]);

    $variant->inventoryItem()->create([
        'quantity_on_hand' => 8,
        'reserved_quantity' => 0,
        'reorder_level' => 2,
        'allow_backorder' => false,
    ]);

    return $product->fresh(['category', 'variants.inventoryItem']);
}

function walkInReviewClaimOrder(Product $product, string $email): Order
{
    $variant = $product->variants()->firstOrFail();

    return tap(Order::query()->create([
        'user_id' => null,
        'source' => 'walk_in',
        'handled_by_user_id' => null,
        'order_number' => 'YSP-CLAIM-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
        'status' => 'completed',
        'payment_status' => 'paid',
        'fulfillment_status' => 'fulfilled',
        'currency' => 'PHP',
        'subtotal_amount' => 5290,
        'discount_amount' => 0,
        'shipping_amount' => 0,
        'tax_amount' => 0,
        'grand_total' => 5290,
        'placed_at' => now(),
        'customer_name' => 'Walk-in Customer',
        'customer_email' => $email,
        'payment_method' => 'cash',
        'metadata' => ['walk_in' => true],
    ]), function (Order $order) use ($product, $variant): void {
        $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_name' => $variant->name,
            'sku' => $variant->sku,
            'quantity' => 1,
            'unit_price' => 5290,
            'line_total' => 5290,
        ]);
    })->fresh(['items', 'reviewClaim']);
}

function walkInReviewClaimRecord(
    Order $order,
    string $plainToken,
    ?Carbon $expiresAt = null,
    array $attributes = [],
): OrderReviewClaim
{
    return OrderReviewClaim::query()->create(array_merge([
        'order_id' => $order->id,
        'claimed_by_user_id' => null,
        'customer_email' => $order->customer_email,
        'token_hash' => hash('sha256', $plainToken),
        'expires_at' => $expiresAt ?? now()->addDays(30),
        'sent_at' => now(),
        'used_at' => null,
    ], $attributes));
}

function walkInReviewClaimPayload(array $overrides = []): array
{
    return array_merge([
        'rating' => 5,
        'title' => 'Verified after store visit',
        'body' => 'The fit stayed consistent, the cushioning felt stable, and the in-store purchase claim worked smoothly.',
    ], $overrides);
}

function walkInReviewClaimShowUrl(OrderReviewClaim $claim, string $plainToken): string
{
    return app(WalkInReviewClaimService::class)->claimUrl($claim, $plainToken);
}

function walkInReviewClaimStoreUrl(OrderReviewClaim $claim, string $plainToken): string
{
    return route('storefront.account.review-claims.store', ['token' => $plainToken]);
}

test('walk in review claim can attach a paid completed walk in order to an existing customer and unlock verified reviews', function () {
    $user = walkInReviewClaimCustomer([
        'email' => 'walker@example.com',
    ]);
    $product = walkInReviewClaimProduct();
    $order = walkInReviewClaimOrder($product, 'walker@example.com');
    $token = str_repeat('a', 64);
    $claim = walkInReviewClaimRecord($order, $token);
    $showUrl = walkInReviewClaimShowUrl($claim, $token);
    $storeUrl = walkInReviewClaimStoreUrl($claim, $token);

    $this->get($showUrl)
        ->assertOk()
        ->assertSeeText('Sign in to claim')
        ->assertSeeText($order->order_number);

    $this->actingAs($user)
        ->get($showUrl)
        ->assertOk()
        ->assertSeeText('Confirm purchase claim');

    $this->actingAs($user)
        ->post($storeUrl)
        ->assertRedirect(route('storefront.account.index'));

    $order->refresh();
    $claim = $order->reviewClaim()->firstOrFail()->fresh();

    expect($order->user_id)->toBe($user->id)
        ->and($claim->claimed_by_user_id)->toBe($user->id)
        ->and($claim->used_at)->not->toBeNull();

    $this->actingAs($user)
        ->post(route('storefront.catalog.products.reviews.store', $product), walkInReviewClaimPayload())
        ->assertRedirect(route('storefront.catalog.products.show', $product).'#reviews');

    $review = ProductReview::query()->firstOrFail();

    expect($review->user_id)->toBe($user->id)
        ->and($review->orderItem?->order_id)->toBe($order->id)
        ->and($review->is_verified_purchase)->toBeTrue();
});

test('walk in review claim supports sign up with intended redirect before the claim is confirmed', function () {
    $product = walkInReviewClaimProduct();
    $order = walkInReviewClaimOrder($product, 'new.walkin@example.com');
    $token = str_repeat('b', 64);
    $claim = walkInReviewClaimRecord($order, $token);
    $claimUrl = walkInReviewClaimShowUrl($claim, $token);

    $response = $this->get($claimUrl)
        ->assertOk()
        ->assertSee(route('register', ['intended' => $claimUrl], false));

    $response->assertSeeText('Create account with this email');
});

test('walk in review claim rejects wrong users expired links and duplicate reuse', function () {
    $rightUser = walkInReviewClaimCustomer([
        'email' => 'claim.right@example.com',
    ]);
    $wrongUser = walkInReviewClaimCustomer([
        'email' => 'claim.wrong@example.com',
    ]);
    $product = walkInReviewClaimProduct();

    $mismatchOrder = walkInReviewClaimOrder($product, 'claim.right@example.com');
    $mismatchToken = str_repeat('c', 64);
    $mismatchClaim = walkInReviewClaimRecord($mismatchOrder, $mismatchToken);
    $mismatchShowUrl = walkInReviewClaimShowUrl($mismatchClaim, $mismatchToken);
    $mismatchStoreUrl = walkInReviewClaimStoreUrl($mismatchClaim, $mismatchToken);

    $this->actingAs($wrongUser)
        ->get($mismatchShowUrl)
        ->assertOk()
        ->assertSeeText('This claim only works with the email address that received the purchase email.');

    $this->from($mismatchShowUrl)
        ->actingAs($wrongUser)
        ->post($mismatchStoreUrl)
        ->assertRedirect($mismatchShowUrl)
        ->assertSessionHasErrors(['claim']);

    $expiredOrder = walkInReviewClaimOrder($product, 'claim.right@example.com');
    $expiredToken = str_repeat('d', 64);
    $expiredClaim = walkInReviewClaimRecord($expiredOrder, $expiredToken, now()->subHour());
    $expiredShowUrl = walkInReviewClaimShowUrl($expiredClaim, $expiredToken);
    $expiredStoreUrl = walkInReviewClaimStoreUrl($expiredClaim, $expiredToken);

    $this->actingAs($rightUser)
        ->get($expiredShowUrl)
        ->assertOk()
        ->assertSeeText('This claim link has expired.');

    $this->from($expiredShowUrl)
        ->actingAs($rightUser)
        ->post($expiredStoreUrl)
        ->assertRedirect($expiredShowUrl)
        ->assertSessionHasErrors(['claim']);

    $claimableOrder = walkInReviewClaimOrder($product, 'claim.right@example.com');
    $claimableToken = str_repeat('e', 64);
    $claimableClaim = walkInReviewClaimRecord($claimableOrder, $claimableToken);
    $claimableShowUrl = walkInReviewClaimShowUrl($claimableClaim, $claimableToken);
    $claimableStoreUrl = walkInReviewClaimStoreUrl($claimableClaim, $claimableToken);

    $this->actingAs($rightUser)
        ->post($claimableStoreUrl)
        ->assertRedirect(route('storefront.account.index'));

    $this->actingAs($rightUser)
        ->get($claimableShowUrl)
        ->assertOk()
        ->assertSeeText('This purchase has already been claimed.');

    $this->from($claimableShowUrl)
        ->actingAs($rightUser)
        ->post($claimableStoreUrl)
        ->assertRedirect($claimableShowUrl)
        ->assertSessionHasErrors(['claim']);
});

test('walk in review claim expiration stays valid immediately and expires on schedule with utc storage timezone', function () {
    config([
        'app.timezone' => 'UTC',
        'app.business_timezone' => 'Asia/Manila',
    ]);

    Carbon::setTestNow(Carbon::parse('2026-05-15 16:05:00', 'UTC'));

    try {
        $product = walkInReviewClaimProduct();
        $order = walkInReviewClaimOrder($product, 'timezone.claim@example.com');
        $token = str_repeat('f', 64);
        $claim = walkInReviewClaimRecord($order, $token, now()->addMinutes(10))->fresh(['order']);
        $service = app(WalkInReviewClaimService::class);
        $showUrl = walkInReviewClaimShowUrl($claim, $token);

        expect($claim->getRawOriginal('expires_at'))->toBe('2026-05-15 16:15:00')
            ->and($service->statusFor($claim, null))->toBe('guest');

        $this->get($showUrl)
            ->assertOk()
            ->assertSeeText('Sign in to claim');

        Carbon::setTestNow(Carbon::parse('2026-05-15 16:15:01', 'UTC'));

        expect($service->statusFor($claim->fresh(['order']), null))->toBe('expired');
    } finally {
        Carbon::setTestNow();
    }
});

test('walk in review claim rejects invalid tokens without opening the claim form', function () {
    $product = walkInReviewClaimProduct();
    walkInReviewClaimOrder($product, 'invalid.claim@example.com');

    $this->get(route('storefront.account.review-claims.show', ['token' => str_repeat('9', 64)]))
        ->assertOk()
        ->assertSeeText('This claim link is not available.');
});

test('walk in review claim email token resolves to the same database claim', function () {
    Mail::fake();

    $product = walkInReviewClaimProduct();
    $order = walkInReviewClaimOrder($product, 'mail.claim@example.com');
    $claim = app(WalkInReviewClaimService::class)->issueAndSendForEligibleOrder($order);

    Mail::assertSent(WalkInReviewClaimMail::class, function (WalkInReviewClaimMail $mail) use ($claim): bool {
        preg_match('#/account/review-claims/([a-f0-9]{64})#', (string) parse_url($mail->claimUrl, PHP_URL_PATH), $matches);
        $plainToken = $matches[1] ?? null;
        $resolved = $plainToken ? app(WalkInReviewClaimService::class)->findByPlainToken($plainToken) : null;

        expect($plainToken)->not->toBeNull()
            ->and($resolved?->id)->toBe($claim?->id)
            ->and($resolved?->token_hash)->toBe(hash('sha256', (string) $plainToken));

        return $mail->claim->is($claim);
    });
});

test('wrong logged in user can switch accounts and return to the same walk in review claim', function () {
    $rightUser = walkInReviewClaimCustomer([
        'email' => 'claim.switch@example.com',
        'password' => 'Password123x',
    ]);
    $admin = walkInReviewClaimAdmin([
        'email' => 'admin.claim.switch@example.com',
        'password' => 'Password123x',
    ]);
    $product = walkInReviewClaimProduct();
    $order = walkInReviewClaimOrder($product, $rightUser->email);
    $token = str_repeat('2', 64);
    $claim = walkInReviewClaimRecord($order, $token);
    $showUrl = walkInReviewClaimShowUrl($claim, $token);

    $this->actingAs($admin)
        ->get($showUrl)
        ->assertOk()
        ->assertSeeText('This claim only works with the email address that received the purchase email.')
        ->assertSee('action="'.route('storefront.account.review-claims.switch-account', ['token' => $token]).'"', escape: false);

    $this->actingAs($admin)
        ->post(route('storefront.account.review-claims.switch-account', ['token' => $token]))
        ->assertRedirect(route('login', ['intended' => $showUrl]))
        ->assertSessionHas('status', 'Sign in with the email address that received this claim link to continue.');

    $this->assertGuest();

    $this->post(route('login.store'), [
        'email' => $rightUser->email,
        'password' => 'Password123x',
        'intended' => $showUrl,
    ])->assertRedirect($showUrl);

    $this->get($showUrl)
        ->assertOk()
        ->assertSeeText('Confirm purchase claim');
});

test('stale switch-account resubmission redirects back to login without a session expired page', function () {
    $rightUser = walkInReviewClaimCustomer([
        'email' => 'claim.stale.switch@example.com',
        'password' => 'Password123x',
    ]);
    $admin = walkInReviewClaimAdmin([
        'email' => 'admin.stale.switch@example.com',
        'password' => 'Password123x',
    ]);
    $product = walkInReviewClaimProduct();
    $order = walkInReviewClaimOrder($product, $rightUser->email);
    $token = str_repeat('3', 64);
    $claim = walkInReviewClaimRecord($order, $token);
    $showUrl = walkInReviewClaimShowUrl($claim, $token);
    $switchUrl = route('storefront.account.review-claims.switch-account', ['token' => $token]);

    $this->actingAs($admin)
        ->withSession(['_token' => 'switch-token'])
        ->post($switchUrl, ['_token' => 'switch-token'])
        ->assertRedirect(route('login', ['intended' => $showUrl]));

    $this->assertGuest();

    $this->followingRedirects()
        ->post($switchUrl, ['_token' => 'switch-token'])
        ->assertOk()
        ->assertDontSeeText('Session expired')
        ->assertSeeText('Sign in');
});

test('guest can sign in from a walk in review claim and return to the same link', function () {
    $user = walkInReviewClaimCustomer([
        'email' => 'claim.guest.login@example.com',
        'password' => 'Password123x',
    ]);
    $product = walkInReviewClaimProduct();
    $order = walkInReviewClaimOrder($product, $user->email);
    $token = str_repeat('1', 64);
    $claim = walkInReviewClaimRecord($order, $token);
    $showUrl = walkInReviewClaimShowUrl($claim, $token);
    $loginUrl = route('login', ['intended' => $showUrl]);

    $this->get($showUrl)
        ->assertOk()
        ->assertSee('href="'.$loginUrl.'"', escape: false)
        ->assertSeeText('Sign in to claim');

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'Password123x',
        'intended' => $showUrl,
    ])->assertRedirect($showUrl);

    $this->get($showUrl)
        ->assertOk()
        ->assertSeeText('Confirm purchase claim');
});

test('admin cannot claim a customer walk in review claim', function () {
    $admin = walkInReviewClaimAdmin([
        'email' => 'admin.blocked@example.com',
    ]);
    $product = walkInReviewClaimProduct();
    $order = walkInReviewClaimOrder($product, 'claim.customer@example.com');
    $token = str_repeat('0', 64);
    $claim = walkInReviewClaimRecord($order, $token);
    $storeUrl = walkInReviewClaimStoreUrl($claim, $token);
    $showUrl = walkInReviewClaimShowUrl($claim, $token);

    $this->from($showUrl)
        ->actingAs($admin)
        ->post($storeUrl)
        ->assertRedirect($showUrl)
        ->assertSessionHasErrors([
            'claim' => 'Sign in with the same email address that received this claim link.',
        ]);

    expect($order->fresh()->user_id)->toBeNull()
        ->and($claim->fresh()->claimed_by_user_id)->toBeNull()
        ->and($claim->fresh()->used_at)->toBeNull();
});

test('used walk in review claim is not treated as expired even when its expiry is in the past', function () {
    $user = walkInReviewClaimCustomer([
        'email' => 'claim.used@example.com',
    ]);
    $product = walkInReviewClaimProduct();
    $order = walkInReviewClaimOrder($product, $user->email);
    $order->forceFill([
        'user_id' => $user->id,
    ])->save();

    $token = str_repeat('7', 64);
    $claim = walkInReviewClaimRecord($order, $token, now()->subDay(), [
        'claimed_by_user_id' => $user->id,
        'used_at' => now()->subHour(),
    ]);

    $this->actingAs($user)
        ->get(walkInReviewClaimShowUrl($claim, $token))
        ->assertOk()
        ->assertSeeText('This purchase has already been claimed.');
});

test('saving sent_at does not change walk in review claim expires_at', function () {
    $product = walkInReviewClaimProduct();
    $order = walkInReviewClaimOrder($product, 'sent.freeze@example.com');
    $token = str_repeat('8', 64);
    $expiresAt = Carbon::parse('2026-06-14 09:51:44', 'UTC');
    $sentAt = Carbon::parse('2026-05-15 09:51:44', 'UTC');
    $claim = walkInReviewClaimRecord($order, $token, $expiresAt, [
        'sent_at' => $sentAt,
    ]);

    $rawExpiresAt = $claim->getRawOriginal('expires_at');

    $claim->forceFill([
        'sent_at' => $sentAt->copy()->addMinutes(5),
    ])->save();

    expect($claim->fresh()->getRawOriginal('expires_at'))->toBe($rawExpiresAt);
});

test('saving used_at does not change walk in review claim expires_at', function () {
    $product = walkInReviewClaimProduct();
    $order = walkInReviewClaimOrder($product, 'used.freeze@example.com');
    $token = str_repeat('6', 64);
    $expiresAt = Carbon::parse('2026-06-14 09:51:44', 'UTC');
    $claim = walkInReviewClaimRecord($order, $token, $expiresAt);
    $rawExpiresAt = $claim->getRawOriginal('expires_at');

    $claim->forceFill([
        'used_at' => now(),
    ])->save();

    expect($claim->fresh()->getRawOriginal('expires_at'))->toBe($rawExpiresAt);
});

test('walk in review claim repair command restores known broken unclaimed claim', function () {
    config([
        'storefront.review_claims.ttl_days' => 30,
    ]);

    $product = walkInReviewClaimProduct();
    $order = walkInReviewClaimOrder($product, 'repairable.claim@example.com');
    $token = str_repeat('5', 64);
    $sentAt = Carbon::parse('2026-05-15 17:51:44', 'UTC');
    $claim = walkInReviewClaimRecord($order, $token, $sentAt, [
        'sent_at' => $sentAt,
        'created_at' => $sentAt->copy()->subSeconds(4),
        'updated_at' => $sentAt,
    ]);

    $this->artisan('review-claims:repair-expiry')
        ->expectsOutputToContain('Walk-in review claim expiry repair completed.')
        ->assertSuccessful();

    expect($claim->fresh()->expires_at?->toIso8601String())
        ->toBe($sentAt->copy()->addDays(30)->toIso8601String());
});

test('walk in review claim repair command does not repair used claims', function () {
    config([
        'storefront.review_claims.ttl_days' => 30,
    ]);

    $user = walkInReviewClaimCustomer([
        'email' => 'repair.used@example.com',
    ]);
    $product = walkInReviewClaimProduct();
    $order = walkInReviewClaimOrder($product, $user->email);
    $token = str_repeat('4', 64);
    $sentAt = Carbon::parse('2026-05-15 17:51:44', 'UTC');
    $claim = walkInReviewClaimRecord($order, $token, $sentAt, [
        'claimed_by_user_id' => $user->id,
        'sent_at' => $sentAt,
        'used_at' => $sentAt->copy()->addMinute(),
    ]);

    $this->artisan('review-claims:repair-expiry')
        ->expectsOutputToContain('Skipped used')
        ->assertSuccessful();

    expect($claim->fresh()->getRawOriginal('expires_at'))->toBe($sentAt->toDateTimeString());
});

test('walk in review claim repair command is idempotent', function () {
    config([
        'storefront.review_claims.ttl_days' => 30,
    ]);

    $product = walkInReviewClaimProduct();
    $order = walkInReviewClaimOrder($product, 'repair.twice@example.com');
    $token = str_repeat('3', 64);
    $sentAt = Carbon::parse('2026-05-15 17:51:44', 'UTC');
    $claim = walkInReviewClaimRecord($order, $token, $sentAt, [
        'sent_at' => $sentAt,
    ]);

    $this->artisan('review-claims:repair-expiry')->assertSuccessful();
    $firstExpiry = $claim->fresh()->getRawOriginal('expires_at');

    $this->artisan('review-claims:repair-expiry')
        ->expectsOutputToContain('Affected')
        ->assertSuccessful();

    expect($claim->fresh()->getRawOriginal('expires_at'))->toBe($firstExpiry);
});

test('walk in review claim mysql schema does not auto update expires_at', function () {
    if (DB::getDriverName() !== 'mysql') {
        expect(true)->toBeTrue();

        return;
    }

    $createTable = DB::selectOne('SHOW CREATE TABLE order_review_claims');
    $sql = strtolower($createTable->{'Create Table'} ?? '');

    expect(str_contains($sql, '`expires_at` datetime'))->toBeTrue()
        ->and((bool) preg_match('/`expires_at`[^,]*default current_timestamp/', $sql))->toBeFalse()
        ->and((bool) preg_match('/`expires_at`[^,]*on update current_timestamp/', $sql))->toBeFalse();
});
