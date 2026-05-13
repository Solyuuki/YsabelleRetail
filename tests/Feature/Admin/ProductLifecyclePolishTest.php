<?php

namespace Tests\Feature\Admin;

use App\Models\Access\Role;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductReview;
use App\Models\Catalog\ProductVariant;
use App\Models\Inventory\StockMovement;
use App\Models\Orders\Order;
use App\Models\Orders\OrderItem;
use App\Models\User;
use App\Services\Catalog\CatalogQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductLifecyclePolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_archived_products_show_restore_action_and_restore_back_to_draft(): void
    {
        $admin = $this->createAdmin();
        $product = $this->createProduct(['status' => 'archived']);

        $this->actingAs($admin)
            ->get(route('admin.catalog.products.edit', $product))
            ->assertOk()
            ->assertSeeText('Restore product')
            ->assertDontSeeText('Archive product');

        $this->actingAs($admin)
            ->patch(route('admin.catalog.products.restore', $product))
            ->assertRedirect(route('admin.catalog.products.index'))
            ->assertSessionHas('toast.title', 'Product restored');

        $this->assertSame('draft', $product->fresh()->status);

        $this->actingAs($admin)
            ->get(route('admin.catalog.products.index'))
            ->assertOk()
            ->assertSeeText($product->name);

        $catalogProducts = app(CatalogQueryService::class)
            ->products(['search' => $product->name], 12)
            ->getCollection();

        $this->assertFalse($catalogProducts->contains(fn (Product $candidate): bool => $candidate->is($product)));
    }

    public function test_delete_blocking_summary_shows_counts_and_recommended_action(): void
    {
        $admin = $this->createAdmin();
        $product = $this->createProduct();
        $variant = $product->variants->firstOrFail();
        $order = Order::query()->create([
            'order_number' => 'ORD-'.Str::upper(Str::random(10)),
            'status' => 'completed',
            'payment_status' => 'paid',
            'fulfillment_status' => 'fulfilled',
            'currency' => 'PHP',
            'subtotal_amount' => 2499,
            'grand_total' => 2499,
            'placed_at' => now(),
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_name' => $variant->name,
            'sku' => $variant->sku,
            'quantity' => 1,
            'unit_price' => 2499,
            'line_total' => 2499,
        ]);

        ProductReview::factory()->for($product)->create();

        StockMovement::query()->create([
            'inventory_item_id' => $variant->inventoryItem->id,
            'product_variant_id' => $variant->id,
            'type' => 'stock_in',
            'quantity_delta' => 3,
            'reference_number' => 'TEST-POLISH',
            'notes' => 'Seed stock movement',
            'occurred_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.catalog.products.edit', $product))
            ->assertOk()
            ->assertSeeText('Delete product unavailable')
            ->assertSeeText('Orders count: 1')
            ->assertSeeText('Reviews count: 1')
            ->assertSeeText('Stock movements count: 1')
            ->assertSeeText('Inventory history: Yes')
            ->assertSeeText('Can delete: No')
            ->assertSeeText('Recommended action: Archive');
    }

    private function createAdmin(): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => 'admin'],
            [
                'name' => 'Admin',
                'description' => 'Admin role',
                'is_system' => true,
            ],
        );

        $user = User::factory()->create();
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function createProduct(array $attributes = [], array $inventoryAttributes = []): Product
    {
        $category = Category::factory()->create(['is_active' => true]);

        $product = Product::factory()
            ->for($category)
            ->create(array_replace([
                'name' => 'Lifecycle Runner',
                'slug' => 'lifecycle-runner-'.Str::lower(Str::random(6)),
                'status' => 'active',
                'is_featured' => true,
                'track_inventory' => true,
            ], $attributes));

        $variant = ProductVariant::factory()
            ->for($product)
            ->create([
                'name' => 'Size 38 / Black',
                'sku' => 'LCY-'.Str::upper(Str::random(8)),
                'price' => 2499,
                'status' => 'active',
            ]);

        $variant->inventoryItem()->create(array_replace([
            'quantity_on_hand' => 8,
            'reserved_quantity' => 0,
            'reorder_level' => 2,
            'allow_backorder' => false,
        ], $inventoryAttributes));

        return $product->fresh(['category', 'variants.inventoryItem']);
    }
}
