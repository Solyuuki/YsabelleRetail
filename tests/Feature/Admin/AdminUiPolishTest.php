<?php

namespace Tests\Feature\Admin;

use App\Models\Access\Role;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Models\Orders\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUiPolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_receipt_view_contains_scoped_print_markup(): void
    {
        $admin = $this->createRoleUser('admin', 'Admin');
        $customer = $this->createRoleUser('customer', 'Customer');
        $product = $this->createOrderProduct();
        $order = $this->createOrder($customer, $product);

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('data-admin-receipt', false)
            ->assertSee('ys-admin-print-hidden', false)
            ->assertSee('ys-admin-print-only', false)
            ->assertSee('ys-admin-receipt-grid', false)
            ->assertSeeText('Print receipt');
    }

    public function test_inventory_dashboard_keeps_import_batches_in_a_secondary_summary_card(): void
    {
        $admin = $this->createRoleUser('admin', 'Admin');

        $this->actingAs($admin)
            ->get(route('admin.inventory.index'))
            ->assertOk()
            ->assertSeeText('Active Variants')
            ->assertSeeText('Units on Hand')
            ->assertSeeText('Low Stock')
            ->assertSeeText('Out of Stock')
            ->assertSeeText('Import Batches')
            ->assertSeeText('Operational import summary')
            ->assertSeeText('Open batch import')
            ->assertSee('ys-admin-inventory-secondary-card', false);
    }

    protected function createRoleUser(string $slug, string $name): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'description' => $name.' role',
                'is_system' => true,
            ],
        );

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    protected function createOrderProduct(): Product
    {
        $category = Category::factory()->create([
            'name' => 'Receipt Category',
            'slug' => 'receipt-category',
            'is_active' => true,
        ]);

        $product = Product::factory()->for($category)->create([
            'name' => 'Receipt Runner',
            'slug' => 'receipt-runner',
            'status' => 'active',
            'primary_image_url' => 'https://cdn.ysabelle.test/catalog/receipt-runner.jpg',
            'image_alt' => 'Receipt Runner product image',
            'image_gallery' => [],
        ]);

        $variant = ProductVariant::factory()->for($product)->create([
            'name' => 'Size 9 / Black',
            'sku' => 'YS-REC-9000-9',
            'option_values' => ['size' => '9', 'color' => 'Black'],
            'status' => 'active',
        ]);

        $variant->inventoryItem()->create([
            'quantity_on_hand' => 8,
            'reserved_quantity' => 0,
            'reorder_level' => 2,
            'allow_backorder' => false,
        ]);

        return $product->fresh(['category', 'variants.inventoryItem']);
    }

    protected function createOrder(User $customer, Product $product): Order
    {
        $variant = $product->variants()->firstOrFail();

        $order = Order::query()->create([
            'user_id' => $customer->id,
            'source' => 'online',
            'order_number' => 'YSR-UI-1001',
            'status' => 'completed',
            'payment_status' => 'paid',
            'fulfillment_status' => 'fulfilled',
            'currency' => 'PHP',
            'subtotal_amount' => 5490,
            'discount_amount' => 0,
            'shipping_amount' => 0,
            'tax_amount' => 0,
            'grand_total' => 5490,
            'placed_at' => now(),
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'payment_method' => 'cash',
            'metadata' => ['source' => 'online'],
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_name' => $variant->name,
            'sku' => $variant->sku,
            'quantity' => 1,
            'unit_price' => 5490,
            'line_total' => 5490,
            'metadata' => [
                'product_image_url' => $product->primary_image_url,
                'product_image_alt' => $product->image_alt,
            ],
        ]);

        return $order->fresh(['items.product', 'items.variant.product']);
    }
}
