<?php

namespace Database\Seeders\Demo;

use App\Models\Access\Role;
use App\Models\Audit\AuditLog;
use App\Models\Cart\Cart;
use App\Models\Catalog\ProductReview;
use App\Models\Catalog\ProductVariant;
use App\Models\Inventory\InventoryImportBatch;
use App\Models\Orders\OrderItem;
use App\Models\Inventory\StockMovement;
use App\Models\Orders\Order;
use App\Models\User;
use App\Services\Admin\WalkInSaleService;
use App\Services\Inventory\InventoryManager;
use App\Services\Storefront\CheckoutService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DemoCommerceSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local') || Order::query()->exists()) {
            return;
        }

        $admin = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('slug', ['admin', 'super-admin']))
            ->first();

        if (! $admin) {
            return;
        }

        $customerRole = Role::query()->where('slug', 'customer')->first();

        if (! $customerRole) {
            return;
        }

        $customers = $this->seedCustomers($customerRole);

        $variants = ProductVariant::query()
            ->with(['product.category', 'inventoryItem'])
            ->where('status', 'active')
            ->orderBy('sku')
            ->get()
            ->keyBy('sku');

        if ($variants->count() < 6) {
            return;
        }

        $inventory = app(InventoryManager::class);
        $checkout = app(CheckoutService::class);
        $walkIn = app(WalkInSaleService::class);

        $batch = InventoryImportBatch::query()->create([
            'reference_number' => 'IMP-DEMO-240401',
            'uploaded_by_user_id' => $admin->id,
            'original_filename' => 'ysabelle-demo-restock.xlsx',
            'status' => 'completed',
            'total_rows' => 3,
            'imported_rows' => 3,
            'failed_rows' => 0,
            'metadata' => ['demo_seed' => true],
        ]);

        foreach ([
            ['sku' => 'YS-AUR-7490-9', 'qty' => 12, 'cost' => 3550.00, 'supplier' => 'North Metro Footwear Hub'],
            ['sku' => 'YS-SHD-6490-8', 'qty' => 8, 'cost' => 2950.00, 'supplier' => 'North Metro Footwear Hub'],
            ['sku' => 'YS-IVR-5890-7', 'qty' => 6, 'cost' => 3100.00, 'supplier' => 'Central Luxe Traders'],
        ] as $line) {
            $movement = $inventory->importStock(
                variant: $variants->get($line['sku']),
                quantity: $line['qty'],
                batch: $batch,
                actor: $admin,
                notes: 'Demo replenishment batch for reporting and stock history.',
                unitCost: $line['cost'],
                supplierName: $line['supplier'],
                metadata: ['demo_seed' => true],
            );

            $this->retimeMovement($movement, Carbon::now()->subDays(18)->setTime(9, 30));
        }

        $batch->forceFill([
            'created_at' => Carbon::now()->subDays(18)->setTime(9, 15),
            'updated_at' => Carbon::now()->subDays(18)->setTime(9, 35),
        ])->save();

        $onlineOrders = [
            [
                'customer' => $customers[0],
                'placed_at' => Carbon::now()->subDays(7)->setTime(10, 15),
                'payment_method' => 'card_simulated',
                'status' => 'completed',
                'payment_status' => 'paid',
                'fulfillment_status' => 'fulfilled',
                'items' => [
                    ['sku' => 'YS-AUR-7490-9', 'quantity' => 1],
                    ['sku' => 'YS-IVR-5890-7', 'quantity' => 1],
                ],
                'shipping' => [
                    'city' => 'Quezon City',
                    'address' => '42 Scout Torillo Street',
                    'postal_code' => '1103',
                ],
            ],
            [
                'customer' => $customers[1],
                'placed_at' => Carbon::now()->subDays(5)->setTime(14, 40),
                'payment_method' => 'cod',
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'fulfillment_status' => 'unfulfilled',
                'items' => [
                    ['sku' => 'YS-SHD-6490-8', 'quantity' => 2],
                ],
                'shipping' => [
                    'city' => 'Pasig City',
                    'address' => '8 Emerald Avenue',
                    'postal_code' => '1605',
                ],
            ],
            [
                'customer' => $customers[2],
                'placed_at' => Carbon::now()->subDays(2)->setTime(19, 10),
                'payment_method' => 'card_simulated',
                'status' => 'completed',
                'payment_status' => 'paid',
                'fulfillment_status' => 'fulfilled',
                'items' => [
                    ['sku' => 'YS-VLT-5790-10', 'quantity' => 1],
                    ['sku' => 'YS-ONX-6290-9', 'quantity' => 1],
                ],
                'shipping' => [
                    'city' => 'Makati City',
                    'address' => '19 Salcedo Street',
                    'postal_code' => '1227',
                ],
            ],
        ];

        foreach ($onlineOrders as $entry) {
            $cart = Cart::query()->create([
                'user_id' => $entry['customer']->id,
                'status' => 'active',
                'currency' => 'PHP',
            ]);

            foreach ($entry['items'] as $item) {
                $variant = $variants->get($item['sku']);

                $cart->items()->create([
                    'product_variant_id' => $variant->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $variant->price,
                    'line_total' => $item['quantity'] * (float) $variant->price,
                    'metadata' => ['demo_seed' => true],
                ]);
            }

            $order = $checkout->placeOrder($cart->fresh(['items.variant.product']), $entry['customer'], [
                'full_name' => $entry['customer']->name,
                'email' => $entry['customer']->email,
                'phone' => $entry['customer']->profile?->mobile_number,
                'city' => $entry['shipping']['city'],
                'address' => $entry['shipping']['address'],
                'postal_code' => $entry['shipping']['postal_code'],
                'payment_method' => $entry['payment_method'],
                'order_notes' => 'Seeded online order for demo reporting.',
                'cardholder_name' => $entry['payment_method'] === 'card_simulated' ? $entry['customer']->name : null,
                'card_number' => $entry['payment_method'] === 'card_simulated' ? '4242424242424242' : null,
                'card_expiry' => $entry['payment_method'] === 'card_simulated' ? '12/30' : null,
                'card_cvc' => $entry['payment_method'] === 'card_simulated' ? '123' : null,
            ]);

            $order->forceFill([
                'status' => $entry['status'],
                'payment_status' => $entry['payment_status'],
                'fulfillment_status' => $entry['fulfillment_status'],
            ])->save();

            $this->retimeOrder($order, $entry['placed_at']);
            $cart->delete();
        }

        foreach ([
            [
                'placed_at' => Carbon::now()->subDays(4)->setTime(11, 20),
                'payment_method' => 'cash',
                'payment_status' => 'paid',
                'customer_name' => '',
                'customer_phone' => null,
                'notes' => 'Walk-in sale from weekend foot traffic.',
                'lines' => [
                    ['variant_id' => $variants->get('YS-AZV-5790-8')->id, 'quantity' => 1],
                    ['variant_id' => $variants->get('YS-SHD-6490-8')->id, 'quantity' => 1],
                ],
            ],
            [
                'placed_at' => Carbon::now()->subDay()->setTime(16, 45),
                'payment_method' => 'gcash',
                'payment_status' => 'paid',
                'customer_name' => 'Nina Santos',
                'customer_phone' => '09175678901',
                'notes' => 'Reserved item picked up in store.',
                'lines' => [
                    ['variant_id' => $variants->get('YS-ONX-6290-9')->id, 'quantity' => 1],
                ],
            ],
        ] as $entry) {
            $order = $walkIn->create([
                'payment_method' => $entry['payment_method'],
                'payment_status' => $entry['payment_status'],
                'customer_name' => $entry['customer_name'],
                'customer_phone' => $entry['customer_phone'],
                'notes' => $entry['notes'],
                'lines' => $entry['lines'],
            ], $admin);

            $this->retimeOrder($order, $entry['placed_at']);
        }

        foreach ([
            ['sku' => 'YS-SHD-6490-8', 'target' => 2, 'time' => Carbon::now()->subHours(8)],
            ['sku' => 'YS-IVR-5890-7', 'target' => 0, 'time' => Carbon::now()->subHours(5)],
            ['sku' => 'YS-VLT-5790-10', 'target' => 3, 'time' => Carbon::now()->subHours(2)],
        ] as $adjustment) {
            $variant = $variants->get($adjustment['sku'])->fresh(['product', 'inventoryItem']);
            $current = (int) ($variant->inventoryItem?->quantity_on_hand ?? 0);

            $movement = $inventory->recordManualChange(
                variant: $variant,
                quantity: $adjustment['target'] - $current,
                type: 'adjustment',
                actor: $admin,
                referenceNumber: 'AUDIT-DEMO',
                notes: 'Cycle count adjustment seeded for demo alerts and reports.',
                metadata: ['demo_seed' => true],
            );

            $this->retimeMovement($movement, $adjustment['time']);
        }

        $this->seedStorefrontReviews($customers, $variants);
    }

    private function seedCustomers(Role $customerRole): Collection
    {
        return collect([
            ['name' => 'Marianne Cruz', 'email' => 'marianne.cruz@ysabelle.demo', 'mobile' => '09171234567'],
            ['name' => 'Paolo Mendoza', 'email' => 'paolo.mendoza@ysabelle.demo', 'mobile' => '09182345678'],
            ['name' => 'Rica Torres', 'email' => 'rica.torres@ysabelle.demo', 'mobile' => '09193456789'],
            ['name' => 'Camille Reyes', 'email' => 'camille.reyes@ysabelle.demo', 'mobile' => '09174560011'],
            ['name' => 'Jared Lim', 'email' => 'jared.lim@ysabelle.demo', 'mobile' => '09174560012'],
            ['name' => 'Nina Santos', 'email' => 'nina.santos@ysabelle.demo', 'mobile' => '09174560013'],
            ['name' => 'Luis Navarro', 'email' => 'luis.navarro@ysabelle.demo', 'mobile' => '09174560014'],
            ['name' => 'Ivy Tan', 'email' => 'ivy.tan@ysabelle.demo', 'mobile' => '09174560015'],
            ['name' => 'Marco Dizon', 'email' => 'marco.dizon@ysabelle.demo', 'mobile' => '09174560016'],
            ['name' => 'Elaine Sy', 'email' => 'elaine.sy@ysabelle.demo', 'mobile' => '09174560017'],
            ['name' => 'Theo Valdez', 'email' => 'theo.valdez@ysabelle.demo', 'mobile' => '09174560018'],
            ['name' => 'Bianca Ong', 'email' => 'bianca.ong@ysabelle.demo', 'mobile' => '09174560019'],
            ['name' => 'Daphne Co', 'email' => 'daphne.co@ysabelle.demo', 'mobile' => '09174560020'],
            ['name' => 'Miguel Pineda', 'email' => 'miguel.pineda@ysabelle.demo', 'mobile' => '09174560021'],
            ['name' => 'Trisha Bautista', 'email' => 'trisha.bautista@ysabelle.demo', 'mobile' => '09174560022'],
            ['name' => 'Anton Villanueva', 'email' => 'anton.villanueva@ysabelle.demo', 'mobile' => '09174560023'],
            ['name' => 'Sofia Chua', 'email' => 'sofia.chua@ysabelle.demo', 'mobile' => '09174560024'],
            ['name' => 'Kian Mercado', 'email' => 'kian.mercado@ysabelle.demo', 'mobile' => '09174560025'],
            ['name' => 'Leah Fernandez', 'email' => 'leah.fernandez@ysabelle.demo', 'mobile' => '09174560026'],
            ['name' => 'Harvey Ramos', 'email' => 'harvey.ramos@ysabelle.demo', 'mobile' => '09174560027'],
        ])->map(function (array $profile) use ($customerRole): User {
            $user = User::query()->firstOrCreate(
                ['email' => $profile['email']],
                [
                    'name' => $profile['name'],
                    'password' => 'Password123x',
                    'status' => 'active',
                ],
            );

            $user->roles()->syncWithoutDetaching([$customerRole->id]);
            $user->profile()->updateOrCreate([], [
                'preferred_name' => $profile['name'],
                'mobile_number' => $profile['mobile'],
            ]);

            return $user;
        })->values();
    }

    private function seedStorefrontReviews(Collection $customers, Collection $variants): void
    {
        $reviewVariants = $variants
            ->filter(fn (ProductVariant $variant): bool => $variant->product !== null && $variant->product->status === 'active')
            ->groupBy('product_id')
            ->map(fn (Collection $group): ProductVariant => $group->sortBy('sku')->first())
            ->sortBy(fn (ProductVariant $variant): string => $variant->product->slug)
            ->values();

        foreach ($reviewVariants as $productIndex => $variant) {
            $product = $variant->product;
            $reviewCount = $product->is_featured ? 3 : (($productIndex % 3) === 0 ? 2 : 1);

            for ($slot = 0; $slot < $reviewCount; $slot++) {
                $customer = $customers[($productIndex + ($slot * 5)) % $customers->count()];
                $rating = $this->ratingFor($productIndex, $slot, (bool) $product->is_featured);
                $placedAt = Carbon::now()->subDays(95 - (($productIndex * 2 + $slot) % 54))->setTime(10 + (($slot + 1) % 6), 20 + (($productIndex + $slot) % 30));
                $reviewedAt = (clone $placedAt)->copy()->addDays(5 + (($productIndex + $slot) % 18))->setTime(18, 15);
                ['title' => $title, 'body' => $body] = $this->reviewContentFor($variant, $productIndex, $slot, $rating);

                $orderItem = $this->createCompletedReviewOrderItem($customer, $variant, $placedAt, $productIndex, $slot);
                $review = ProductReview::factory()->verified($orderItem)->create([
                    'rating' => $rating,
                    'title' => $title,
                    'body' => $body,
                    'is_visible' => true,
                ]);

                $review->forceFill([
                    'created_at' => $reviewedAt,
                    'updated_at' => $reviewedAt,
                ])->saveQuietly();
            }
        }
    }

    private function createCompletedReviewOrderItem(
        User $customer,
        ProductVariant $variant,
        Carbon $placedAt,
        int $productIndex,
        int $slot,
    ): OrderItem {
        $product = $variant->product;
        $unitPrice = (float) $variant->price;
        $order = Order::query()->create([
            'user_id' => $customer->id,
            'source' => 'storefront',
            'order_number' => sprintf('ORD-RVW-%03d-%02d-%02d', $product->id, $productIndex % 100, $slot + 1),
            'status' => 'completed',
            'payment_status' => 'paid',
            'fulfillment_status' => 'fulfilled',
            'currency' => 'PHP',
            'subtotal_amount' => $unitPrice,
            'discount_amount' => 0,
            'shipping_amount' => 0,
            'tax_amount' => 0,
            'grand_total' => $unitPrice,
            'placed_at' => $placedAt,
            'notes' => 'Demo verified-purchase order seeded for storefront review content.',
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->profile?->mobile_number,
            'shipping_city' => $this->shippingCities()[($productIndex + $slot) % count($this->shippingCities())],
            'shipping_address_line' => sprintf('%d Demo Commerce Street', 18 + (($productIndex + $slot) % 70)),
            'shipping_postal_code' => (string) (1100 + (($productIndex + $slot) % 180)),
            'payment_method' => (($productIndex + $slot) % 4 === 0) ? 'cod' : 'card_simulated',
            'metadata' => [
                'demo_seed' => true,
                'review_seed' => true,
            ],
        ]);

        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_name' => $variant->name,
            'sku' => $variant->sku,
            'quantity' => 1,
            'unit_price' => $unitPrice,
            'line_total' => $unitPrice,
            'metadata' => [
                'demo_seed' => true,
                'review_seed' => true,
            ],
        ]);

        $this->retimeOrder($order->fresh('items'), $placedAt);

        return $item->fresh();
    }

    private function ratingFor(int $productIndex, int $slot, bool $featured): int
    {
        $featuredRatings = [5, 5, 4, 5, 4, 5];
        $standardRatings = [5, 4, 5, 4, 3, 5, 4, 4, 5, 3, 4, 5];

        $ratings = $featured ? $featuredRatings : $standardRatings;

        return $ratings[($productIndex + $slot) % count($ratings)];
    }

    private function reviewContentFor(ProductVariant $variant, int $productIndex, int $slot, int $rating): array
    {
        $product = $variant->product;
        $categoryName = $product->category?->name ?? 'Footwear';
        $contextPool = $this->categoryContexts()[$categoryName] ?? ['daily wear', 'long walks', 'weekend errands'];
        $comfortPool = $this->comfortNotes();
        $fitPool = $rating >= 4 ? $this->fitNotes() : $this->balancedFitNotes();
        $positivePool = $this->positiveClosings();
        $balancedPool = $this->balancedClosings();

        $context = $contextPool[($productIndex + $slot) % count($contextPool)];
        $comfort = $comfortPool[($productIndex + ($slot * 2)) % count($comfortPool)];
        $fit = $fitPool[($productIndex + ($slot * 3)) % count($fitPool)];
        $closingPool = $rating >= 4 ? $positivePool : $balancedPool;
        $closing = $closingPool[($productIndex + $slot) % count($closingPool)];

        $titles = match (true) {
            $rating === 5 => [
                'Easy to recommend',
                'Strong comfort from day one',
                'A pair I keep reaching for',
                'Premium feel without the fuss',
            ],
            $rating === 4 => [
                'Great daily pickup',
                'Solid overall with one small note',
                'Comfortable and reliable',
                'Good value for the wear',
            ],
            default => [
                'Good, but fit is a little specific',
                'Nice pair with a small tradeoff',
                'Works well after some break-in',
                'Comfortable, just not perfect',
            ],
        };

        $title = $titles[($productIndex + $slot) % count($titles)];
        $body = match (true) {
            $rating === 5 => "I bought the {$product->name} for {$context} and it settled in quickly. The {$comfort} and {$fit}. {$closing}",
            $rating === 4 => "I've been using the {$product->name} for {$context}. The {$comfort}, and {$fit}. {$closing}",
            default => "The {$product->name} has been good for {$context}. The {$comfort}, but {$fit}. {$closing}",
        };

        return [
            'title' => $title,
            'body' => $body,
        ];
    }

    private function categoryContexts(): array
    {
        return [
            'Running Shoes' => ['tempo runs before work', 'weekend 5K sessions', 'easy miles and long walks'],
            'Sneakers' => ['office days and dinner plans', 'daily commuting', 'weekend mall errands'],
            'Basketball Shoes' => ['indoor pickup games', 'short shooting sessions', 'practice nights on polished courts'],
            'Lifestyle Shoes' => ['city walks and casual dinners', 'coffee runs and weekend meetups', 'travel days with light walking'],
            'Training Shoes' => ['gym circuits and short treadmill blocks', 'strength sessions and quick errands', 'mixed training days'],
            'Walking Shoes' => ['all-day errands', 'mall walks with the family', 'long workdays on my feet'],
            'Slip-ons' => ['quick grocery runs', 'airport days and easy commuting', 'casual weekend wear'],
            'Boots / High-cut Shoes' => ['rainy commutes', 'weekend city walks', 'cool-weather daily wear'],
        ];
    }

    private function comfortNotes(): array
    {
        return [
            'cushioning feels supportive without getting mushy',
            'step-in comfort is noticeably better than I expected',
            'underfoot feel stays stable even after a few hours',
            'padding feels balanced and never overly bulky',
            'ride feels smooth enough for repeat wear through the week',
        ];
    }

    private function fitNotes(): array
    {
        return [
            'the fit stayed secure through the midfoot and never felt sloppy',
            'the shape worked well for my usual size after the first wear',
            'the forefoot had enough room while still feeling locked in',
            'the upper softened nicely after a short break-in',
            'I would say it fits true to size for a regular-width foot',
        ];
    }

    private function balancedFitNotes(): array
    {
        return [
            'the forefoot felt a little snug until the upper loosened up',
            'I liked the shape overall, though it needed a short break-in',
            'the heel felt secure, but the first wear was firmer than expected',
            'I would stay with my usual size, but wider feet may want extra room',
            'the fit was decent overall, though it is not the plushest pair I own',
        ];
    }

    private function positiveClosings(): array
    {
        return [
            'I would buy another color if one opens up.',
            'It still looks polished even after regular use.',
            'For the price, it feels thoughtfully finished.',
            'This is one of the easier pairs in my rotation to recommend.',
            'I can wear it for hours without wanting to swap out early.',
        ];
    }

    private function balancedClosings(): array
    {
        return [
            'I just wish the first wear had a little more give.',
            'It improved after a few uses, but it is not the softest pair I own.',
            'I still like it overall, though I would avoid sizing down.',
            'It does the job well, but the fit may depend on foot shape.',
            'I would keep it in rotation, just not for my longest days.',
        ];
    }

    private function shippingCities(): array
    {
        return [
            'Quezon City',
            'Makati City',
            'Pasig City',
            'Taguig City',
            'Mandaluyong City',
            'Paranaque City',
            'San Juan City',
            'Marikina City',
        ];
    }

    private function retimeOrder(Order $order, Carbon $placedAt): void
    {
        $order->forceFill([
            'placed_at' => $placedAt,
            'created_at' => $placedAt,
            'updated_at' => $placedAt,
        ])->save();

        $order->payments()->get()->each(function ($payment) use ($placedAt): void {
            $payment->forceFill([
                'paid_at' => $payment->paid_at ? $placedAt : null,
                'created_at' => $placedAt,
                'updated_at' => $placedAt,
            ])->save();
        });

        $order->items()->get()->each(function ($item) use ($placedAt): void {
            $item->forceFill([
                'created_at' => $placedAt,
                'updated_at' => $placedAt,
            ])->save();
        });

        $order->stockMovements()->get()->each(fn (StockMovement $movement) => $this->retimeMovement($movement, $placedAt));

        AuditLog::query()
            ->where('subject_type', $order->getMorphClass())
            ->where('subject_id', $order->getKey())
            ->update([
                'created_at' => $placedAt,
                'updated_at' => $placedAt,
            ]);
    }

    private function retimeMovement(StockMovement $movement, Carbon $occurredAt): void
    {
        $movement->forceFill([
            'occurred_at' => $occurredAt,
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ])->save();

        AuditLog::query()
            ->where('subject_type', $movement->getMorphClass())
            ->where('subject_id', $movement->getKey())
            ->update([
                'created_at' => $occurredAt,
                'updated_at' => $occurredAt,
            ]);
    }
}
