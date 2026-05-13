<?php

namespace Tests\Feature\Admin;

use App\Models\Access\Role;
use App\Models\Audit\AuditLog;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Models\Inventory\StockMovement;
use App\Models\Orders\Order;
use App\Models\User;
use App\Services\Admin\AdminActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditLogReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_page_links_to_audit_logs_and_admin_can_open_it(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSeeText('Open Audit Logs');

        $this->actingAs($admin)
            ->get(route('admin.reports.audit-logs.index'))
            ->assertOk()
            ->assertSeeText('Audit Logs')
            ->assertSeeText('Action Type')
            ->assertSeeText('Actor')
            ->assertSeeText('Entity Type');
    }

    public function test_non_admin_users_cannot_access_or_export_audit_logs(): void
    {
        $customer = $this->createCustomer();

        $this->actingAs($customer)
            ->get(route('admin.reports.audit-logs.index'))
            ->assertForbidden();

        $this->actingAs($customer)
            ->get(route('admin.reports.audit-logs.export', ['format' => 'csv']))
            ->assertForbidden();
    }

    public function test_audit_logs_page_displays_and_filters_persistent_entries(): void
    {
        $admin = $this->createAdmin(['email' => 'audit-admin@example.com']);
        $secondAdmin = $this->createAdmin(['email' => 'stock-admin@example.com']);

        $orderLog = $this->createOrderAuditLog($admin, Carbon::parse('2026-05-10 09:30:00'));
        $inventoryLog = $this->createInventoryAuditLog($secondAdmin, Carbon::parse('2026-05-14 14:15:00'));

        $this->actingAs($admin)
            ->get(route('admin.reports.audit-logs.index'))
            ->assertOk()
            ->assertSeeText('Walk In Sale Completed')
            ->assertSeeText('Stock Changed')
            ->assertSeeText($admin->name)
            ->assertSeeText($secondAdmin->name)
            ->assertSeeText($orderLog->metadata['order_number'])
            ->assertSeeText($inventoryLog->metadata['sku']);

        $this->actingAs($admin)
            ->get(route('admin.reports.audit-logs.index', [
                'action' => 'inventory.stock_changed',
                'actor_id' => $secondAdmin->id,
                'entity_type' => $inventoryLog->subject_type,
                'date_from' => '2026-05-14',
                'date_to' => '2026-05-14',
            ]))
            ->assertOk()
            ->assertSeeText('Stock Changed')
            ->assertSeeText($secondAdmin->name)
            ->assertSeeText($inventoryLog->metadata['sku'])
            ->assertDontSeeText($orderLog->metadata['order_number']);
    }

    public function test_audit_log_exports_work_and_existing_sales_report_export_still_works(): void
    {
        $admin = $this->createAdmin(['email' => 'exports-admin@example.com']);
        $inventoryLog = $this->createInventoryAuditLog($admin, Carbon::parse('2026-05-14 16:45:00'));

        $csvResponse = $this->actingAs($admin)
            ->get(route('admin.reports.audit-logs.export', [
                'format' => 'csv',
                'action' => 'inventory.stock_changed',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('Audit Logs', $csvResponse->streamedContent());
        $this->assertStringContainsString($inventoryLog->metadata['sku'], $csvResponse->streamedContent());

        $this->actingAs($admin)
            ->get(route('admin.reports.audit-logs.export', ['format' => 'pdf']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($admin)
            ->get(route('admin.reports.audit-logs.export', ['format' => 'xlsx']))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($admin)
            ->get(route('admin.reports.export', [
                'report' => 'sales',
                'format' => 'csv',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
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

    private function createCustomer(array $attributes = []): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => 'customer'],
            [
                'name' => 'Customer',
                'description' => 'Customer role',
                'is_system' => true,
            ],
        );

        $user = User::factory()->create($attributes);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function createInventoryAuditLog(User $actor, Carbon $createdAt): AuditLog
    {
        $category = Category::factory()->create(['name' => 'Audit Running Shoes']);
        $product = Product::factory()->for($category)->create([
            'name' => 'Audit Runner',
            'slug' => 'audit-runner-'.Str::lower(Str::random(6)),
            'status' => 'active',
        ]);

        $variant = ProductVariant::factory()->for($product)->create([
            'name' => 'Size 39 / Black',
            'sku' => 'AUD-'.Str::upper(Str::random(8)),
            'option_values' => ['size' => '39', 'color' => 'Black'],
            'price' => 2499,
            'status' => 'active',
        ]);

        $inventoryItem = $variant->inventoryItem()->create([
            'quantity_on_hand' => 5,
            'reserved_quantity' => 0,
            'reorder_level' => 2,
            'allow_backorder' => false,
        ]);

        $movement = StockMovement::query()->create([
            'inventory_item_id' => $inventoryItem->id,
            'product_variant_id' => $variant->id,
            'actor_id' => $actor->id,
            'type' => 'adjustment',
            'quantity_delta' => -2,
            'reference_number' => 'AUDIT-'.Str::upper(Str::random(6)),
            'notes' => 'Audit log report seed.',
            'occurred_at' => $createdAt,
        ]);

        app(AdminActivityLogger::class)->recordInventory($movement->fresh(['inventoryItem', 'variant.product', 'order']), 5);

        $log = AuditLog::query()
            ->where('subject_type', $movement->getMorphClass())
            ->where('subject_id', $movement->id)
            ->firstOrFail();

        $log->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $log->fresh('actor');
    }

    private function createOrderAuditLog(User $actor, Carbon $createdAt): AuditLog
    {
        $order = Order::query()->create([
            'source' => 'walk_in',
            'handled_by_user_id' => $actor->id,
            'order_number' => 'YSP-AUDIT-'.Str::upper(Str::random(6)),
            'status' => 'completed',
            'payment_status' => 'paid',
            'fulfillment_status' => 'fulfilled',
            'currency' => 'PHP',
            'subtotal_amount' => 2400,
            'discount_amount' => 0,
            'shipping_amount' => 0,
            'tax_amount' => 0,
            'grand_total' => 2400,
            'placed_at' => $createdAt,
            'customer_name' => 'Audit Buyer',
            'payment_method' => 'cash',
            'metadata' => ['audit_seed' => true],
        ]);

        app(AdminActivityLogger::class)->recordOrder($order);

        $log = AuditLog::query()
            ->where('subject_type', $order->getMorphClass())
            ->where('subject_id', $order->id)
            ->firstOrFail();

        $log->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $log->fresh('actor');
    }
}
