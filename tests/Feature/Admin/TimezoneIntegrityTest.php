<?php

namespace Tests\Feature\Admin;

use App\Models\Access\Role;
use App\Models\Audit\AuditLog;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Models\Inventory\InventoryImportBatch;
use App\Models\Inventory\StockMovement;
use App\Models\Orders\Order;
use App\Models\User;
use App\Services\Admin\AdminDashboardService;
use App\Services\Admin\WalkInSaleService;
use App\Services\Inventory\BatchStockImportService;
use App\Support\Admin\InventoryMovementType;
use App\Support\BusinessTime;
use App\Support\OrderNumberGenerator;
use App\Support\SupportTicketNumberGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class TimezoneIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.timezone' => 'UTC',
            'app.business_timezone' => 'Asia/Manila',
            'database.connections.mysql.timezone' => '+00:00',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_inventory_and_audit_views_render_business_local_timestamps(): void
    {
        $admin = $this->createAdmin();
        $variant = $this->createInventoryVariant();
        $timestamp = CarbonImmutable::parse('2026-05-14 16:00:37', 'UTC');

        $batch = InventoryImportBatch::query()->create([
            'reference_number' => 'IMP-TIME-0001',
            'uploaded_by_user_id' => $admin->id,
            'original_filename' => 'timezone-check.csv',
            'status' => 'completed',
            'total_rows' => 1,
            'imported_rows' => 1,
            'failed_rows' => 0,
            'metadata' => [],
        ]);

        $batch->forceFill([
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->save();

        $movement = StockMovement::query()->create([
            'inventory_item_id' => $variant->inventoryItem->id,
            'product_variant_id' => $variant->id,
            'import_batch_id' => $batch->id,
            'actor_id' => $admin->id,
            'type' => InventoryMovementType::BATCH_IMPORT,
            'quantity_delta' => 5,
            'reference_number' => $batch->reference_number,
            'notes' => 'Timezone verification import.',
            'metadata' => ['sku' => $variant->sku],
            'occurred_at' => $timestamp,
        ]);

        $log = AuditLog::query()->create([
            'actor_id' => $admin->id,
            'event' => 'inventory.stock_changed',
            'subject_type' => $movement->getMorphClass(),
            'subject_id' => $movement->id,
            'metadata' => [
                'product_name' => $variant->product->name,
                'sku' => $variant->sku,
                'quantity_delta' => 5,
                'current_quantity' => 12,
                'movement_type' => InventoryMovementType::BATCH_IMPORT,
                'stock_status' => 'healthy',
            ],
        ]);

        $log->forceFill([
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->save();

        $localizedInventoryTime = 'May 15, 2026 12:00 AM';
        $localizedAuditTime = '2026-05-15 00:00:37';

        $this->actingAs($admin)
            ->get(route('admin.inventory.index', ['tab' => 'movements']))
            ->assertOk()
            ->assertSeeText($localizedInventoryTime);

        $this->actingAs($admin)
            ->get(route('admin.reports.audit-logs.index'))
            ->assertOk()
            ->assertSeeText($localizedAuditTime);

        $this->assertSame($localizedInventoryTime, BusinessTime::format($batch->fresh()->created_at, 'M d, Y h:i A'));
        $this->assertSame($localizedInventoryTime, BusinessTime::format($movement->fresh()->occurred_at, 'M d, Y h:i A'));
    }

    public function test_sales_reports_and_exports_use_business_day_boundaries(): void
    {
        $admin = $this->createAdmin();
        $this->freezeUtcNow('2026-05-15 01:30:00');

        $excludedOrder = $this->createOrder([
            'order_number' => 'YSB-EXCLUDED-001',
            'placed_at' => CarbonImmutable::parse('2026-05-14 15:59:59', 'UTC'),
            'customer_name' => 'Before Boundary',
        ]);

        $includedOrder = $this->createOrder([
            'order_number' => 'YSB-INCLUDED-001',
            'placed_at' => CarbonImmutable::parse('2026-05-14 16:00:00', 'UTC'),
            'customer_name' => 'After Boundary',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'report' => 'sales',
                'date_from' => '2026-05-15',
                'date_to' => '2026-05-15',
            ]))
            ->assertOk()
            ->assertSeeText($includedOrder->order_number)
            ->assertDontSeeText($excludedOrder->order_number)
            ->assertSeeText('2026-05-15 00:00');

        $csvResponse = $this->actingAs($admin)
            ->get(route('admin.reports.export', [
                'report' => 'sales',
                'format' => 'csv',
                'date_from' => '2026-05-15',
                'date_to' => '2026-05-15',
            ]))
            ->assertOk();

        $content = $csvResponse->streamedContent();

        $this->assertStringContainsString('"Generated Date","2026-05-15 09:30:00"', $content);
        $this->assertStringContainsString($includedOrder->order_number, $content);
        $this->assertStringContainsString('2026-05-15 00:00', $content);
        $this->assertStringNotContainsString($excludedOrder->order_number, $content);
    }

    public function test_dashboard_sales_chart_groups_completed_orders_by_business_day(): void
    {
        $this->freezeUtcNow('2026-05-15 01:30:00');

        $this->createOrder([
            'order_number' => 'YSB-CHART-001',
            'placed_at' => CarbonImmutable::parse('2026-05-14 15:59:59', 'UTC'),
            'grand_total' => 1500,
        ]);

        $includedOrder = $this->createOrder([
            'order_number' => 'YSB-CHART-002',
            'placed_at' => CarbonImmutable::parse('2026-05-14 16:00:00', 'UTC'),
            'grand_total' => 2750,
        ]);

        $salesChart = app(AdminDashboardService::class)->summary()['sales_chart']
            ->keyBy('date');

        $this->assertSame(1, $salesChart['2026-05-15']['orders_count']);
        $this->assertSame((float) $includedOrder->grand_total, $salesChart['2026-05-15']['total']);
        $this->assertSame(1, $salesChart['2026-05-14']['orders_count']);
    }

    public function test_business_date_references_follow_business_timezone(): void
    {
        $admin = $this->createAdmin();
        $variant = $this->createInventoryVariant();
        $this->freezeUtcNow('2026-05-14 18:30:00');

        $this->assertStringStartsWith('YSB-260515-', app(OrderNumberGenerator::class)->generate('YSB'));
        $this->assertStringStartsWith('YR-SUP-20260515-', app(SupportTicketNumberGenerator::class)->generate());

        $batch = app(BatchStockImportService::class)->commit([
            'filename' => 'timezone-reference.csv',
            'token' => (string) Str::uuid(),
            'rows' => [[
                'row_number' => 2,
                'values' => [
                    'sku' => $variant->sku,
                    'product_name' => $variant->product->name,
                    'variant' => $variant->name,
                    'quantity' => 3,
                    'cost_price' => 1250.50,
                    'supplier' => 'Timezone Supplier',
                    'notes' => 'Reference timestamp check',
                ],
                'variant_id' => $variant->id,
                'product_name' => $variant->product->name,
                'variant_name' => $variant->name,
                'errors' => [],
            ]],
        ], $admin);

        $this->assertStringStartsWith('IMP-260515023000-', $batch->reference_number);
    }

    public function test_walk_in_order_and_payment_timestamps_align_when_localized(): void
    {
        $cashier = $this->createAdmin();
        $variant = $this->createInventoryVariant(quantity: 10);
        $this->freezeUtcNow('2026-05-14 16:05:00');

        $order = app(WalkInSaleService::class)->create([
            'customer_name' => 'Counter Buyer',
            'customer_email' => null,
            'customer_phone' => '09171234567',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'discount_amount' => 0,
            'notes' => 'Timezone alignment check',
            'lines' => [[
                'variant_id' => $variant->id,
                'quantity' => 2,
            ]],
        ], $cashier)->fresh(['payments']);

        $payment = $order->payments->firstOrFail();
        $localizedOrderTime = BusinessTime::format($order->placed_at, 'Y-m-d H:i:s');
        $localizedPaymentTime = BusinessTime::format($payment->paid_at, 'Y-m-d H:i:s');

        $this->assertSame('2026-05-15 00:05:00', $localizedOrderTime);
        $this->assertSame($localizedOrderTime, $localizedPaymentTime);
    }

    private function freezeUtcNow(string $utcTimestamp): void
    {
        $now = CarbonImmutable::parse($utcTimestamp, 'UTC');

        Carbon::setTestNow($now);
        CarbonImmutable::setTestNow($now);
    }

    private function createAdmin(array $attributes = []): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => 'admin'],
            [
                'name' => 'Admin',
                'description' => 'Admin role',
                'is_system' => true,
            ],
        );

        $user = User::factory()->create($attributes);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function createInventoryVariant(int $quantity = 7): ProductVariant
    {
        $variant = ProductVariant::factory()
            ->for(Product::factory()->state([
                'name' => 'Timezone Runner',
                'slug' => 'timezone-runner-'.Str::lower(Str::random(6)),
                'track_inventory' => true,
                'status' => 'active',
            ]))
            ->create([
                'name' => 'Size 39 / Black',
                'status' => 'active',
            ]);

        $variant->inventoryItem()->create([
            'quantity_on_hand' => $quantity,
            'reserved_quantity' => 0,
            'reorder_level' => 2,
            'allow_backorder' => false,
        ]);

        return $variant->fresh(['product', 'inventoryItem']);
    }

    private function createOrder(array $overrides = []): Order
    {
        return Order::query()->create(array_merge([
            'source' => 'online',
            'order_number' => 'YSB-'.Str::upper(Str::random(8)),
            'status' => 'completed',
            'payment_status' => 'paid',
            'fulfillment_status' => 'fulfilled',
            'currency' => 'PHP',
            'subtotal_amount' => 2500,
            'discount_amount' => 0,
            'shipping_amount' => 0,
            'tax_amount' => 0,
            'grand_total' => 2500,
            'placed_at' => now(),
            'customer_name' => 'Timezone Customer',
            'customer_email' => 'timezone@example.com',
            'payment_method' => 'card_simulated',
            'metadata' => ['timezone_test' => true],
        ], $overrides));
    }
}
