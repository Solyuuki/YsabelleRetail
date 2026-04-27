<?php

use App\Models\Access\Role;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Models\Inventory\InventoryImportBatch;
use App\Models\Inventory\StockMovement;
use App\Models\Orders\Order;
use App\Models\User;
use App\Support\Admin\InventoryMovementType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

function createAdminUser(array $attributes = []): User
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
    $user->roles()->attach($role);

    return $user;
}

function createCustomerUser(array $attributes = []): User
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
    $user->roles()->attach($role);

    return $user;
}

function createInventoryVariant(int $quantity = 8, array $variantOverrides = [], array $productOverrides = []): ProductVariant
{
    $variant = ProductVariant::factory()
        ->for(Product::factory()->state($productOverrides))
        ->create($variantOverrides);

    $variant->inventoryItem()->create([
        'quantity_on_hand' => $quantity,
        'reserved_quantity' => 0,
        'reorder_level' => 2,
        'allow_backorder' => false,
    ]);

    return $variant->fresh(['product', 'inventoryItem']);
}

test('guest users are redirected and customers are forbidden across company admin routes', function () {
    $routes = [
        'admin.dashboard',
        'admin.catalog.products.index',
        'admin.catalog.categories.index',
        'admin.inventory.index',
        'admin.inventory.manual-import.create',
        'admin.inventory.batch-imports.create',
        'admin.pos.create',
        'admin.orders.index',
        'admin.customers.index',
        'admin.reports.index',
    ];

    foreach ($routes as $route) {
        $this->get(route($route))->assertRedirect(route('login'));
    }

    $customer = createCustomerUser();

    foreach ($routes as $route) {
        $this->actingAs($customer)->get(route($route))->assertForbidden();
    }
});

test('admin can create update and archive products with tracked inventory', function () {
    $admin = createAdminUser();
    $category = Category::factory()->create();

    $payload = [
        'category_id' => $category->id,
        'name' => 'Production Runner',
        'slug' => 'production-runner',
        'style_code' => 'YS-9001',
        'short_description' => 'Company-grade release',
        'description' => 'A polished product record for the admin back office.',
        'primary_image_url' => 'https://example.com/runner.jpg',
        'image_alt' => 'Production Runner image',
        'status' => 'active',
        'is_featured' => '1',
        'featured_rank' => '1',
        'track_inventory' => '1',
        'variants' => [
            [
                'name' => 'Size 38 / Black',
                'sku' => 'YSV-PRD-001',
                'barcode' => '1234567890123',
                'size' => '38',
                'color' => 'Black',
                'price' => '2499',
                'compare_at_price' => '2899',
                'cost_price' => '1300',
                'supplier_name' => 'Prime Supplier',
                'weight_grams' => '700',
                'status' => 'active',
                'quantity_on_hand' => '14',
                'reorder_level' => '3',
                'allow_backorder' => '0',
            ],
        ],
    ];

    $this->actingAs($admin)
        ->post(route('admin.catalog.products.store'), $payload)
        ->assertRedirect();

    $product = Product::query()->where('slug', 'production-runner')->firstOrFail();
    $variant = $product->variants()->firstOrFail();

    expect($product->track_inventory)->toBeTrue()
        ->and((int) $variant->inventoryItem->quantity_on_hand)->toBe(14);

    $updatePayload = $payload;
    $updatePayload['name'] = 'Production Runner II';
    $updatePayload['variants'][0]['id'] = $variant->id;
    $updatePayload['variants'][0]['quantity_on_hand'] = '18';
    $updatePayload['variants'][0]['cost_price'] = '1450';

    $this->actingAs($admin)
        ->put(route('admin.catalog.products.update', $product), $updatePayload)
        ->assertRedirect(route('admin.catalog.products.edit', $product->fresh()));

    $product->refresh();
    $variant->refresh();
    $variant->load('inventoryItem');

    expect($product->name)->toBe('Production Runner II')
        ->and((float) $variant->cost_price)->toBe(1450.0)
        ->and((int) $variant->inventoryItem->quantity_on_hand)->toBe(18);

    $this->actingAs($admin)
        ->delete(route('admin.catalog.products.destroy', $product))
        ->assertRedirect(route('admin.catalog.products.index'));

    expect($product->fresh()->status)->toBe('archived');
});

test('product form rejects duplicate skus already assigned to another variant', function () {
    $admin = createAdminUser();
    $category = Category::factory()->create();
    ProductVariant::factory()->create(['sku' => 'YSV-DUP-001']);

    $this->from(route('admin.catalog.products.create'))
        ->actingAs($admin)
        ->post(route('admin.catalog.products.store'), [
            'category_id' => $category->id,
            'name' => 'Duplicate SKU Product',
            'slug' => 'duplicate-sku-product',
            'style_code' => 'YS-9002',
            'short_description' => 'Duplicate check',
            'description' => 'Testing SKU validation.',
            'primary_image_url' => 'https://example.com/duplicate.jpg',
            'image_alt' => 'Duplicate SKU Product image',
            'status' => 'active',
            'is_featured' => '0',
            'featured_rank' => '',
            'track_inventory' => '1',
            'variants' => [
                [
                    'name' => 'Default',
                    'sku' => 'YSV-DUP-001',
                    'barcode' => '',
                    'size' => '',
                    'color' => '',
                    'price' => '1999',
                    'compare_at_price' => '',
                    'cost_price' => '999',
                    'supplier_name' => '',
                    'weight_grams' => '550',
                    'status' => 'active',
                    'quantity_on_hand' => '4',
                    'reorder_level' => '1',
                    'allow_backorder' => '0',
                ],
            ],
        ])
        ->assertRedirect(route('admin.catalog.products.create'))
        ->assertSessionHasErrors(['variants']);
});

test('category deletion is blocked while active products still belong to it', function () {
    $admin = createAdminUser();
    $category = Category::factory()->create();
    Product::factory()->for($category)->create(['status' => 'active']);

    $this->from(route('admin.catalog.categories.index'))
        ->actingAs($admin)
        ->delete(route('admin.catalog.categories.destroy', $category))
        ->assertRedirect(route('admin.catalog.categories.index'))
        ->assertSessionHas('toast');

    $this->assertDatabaseHas('categories', ['id' => $category->id]);
});

test('manual stock import records stock in movements and updates on-hand quantity', function () {
    $admin = createAdminUser();
    $variant = createInventoryVariant(5);

    $this->actingAs($admin)
        ->post(route('admin.inventory.manual-import.store'), [
            'product_variant_id' => $variant->id,
            'type' => InventoryMovementType::STOCK_IN,
            'quantity' => 3,
            'cost_price' => '1450.50',
            'supplier_name' => 'Warehouse Supplier',
            'reference_number' => 'MAN-1001',
            'notes' => 'Restocked from warehouse.',
        ])
        ->assertRedirect(route('admin.inventory.index'));

    $variant->refresh();
    $variant->load('inventoryItem');

    expect((int) $variant->inventoryItem->quantity_on_hand)->toBe(8)
        ->and((float) $variant->cost_price)->toBe(1450.5)
        ->and($variant->supplier_name)->toBe('Warehouse Supplier');

    $this->assertDatabaseHas('stock_movements', [
        'product_variant_id' => $variant->id,
        'type' => InventoryMovementType::STOCK_IN,
        'quantity_delta' => 3,
        'reference_number' => 'MAN-1001',
        'actor_id' => $admin->id,
    ]);
});

test('batch stock import previews commits and records inventory movements', function () {
    $admin = createAdminUser();
    $variant = createInventoryVariant(4, ['sku' => 'YSV-IMPORT-001']);

    $file = UploadedFile::fake()->createWithContent(
        'stock-import.csv',
        "sku,product_name,variant,quantity,cost_price,supplier,notes\nYSV-IMPORT-001,{$variant->product->name},{$variant->name},6,1750.00,Import Supplier,Delivery batch\n",
    );

    $this->actingAs($admin)
        ->post(route('admin.inventory.batch-imports.preview'), [
            'file' => $file,
        ])
        ->assertRedirect(route('admin.inventory.batch-imports.create'));

    $preview = session('inventory_import_preview');

    expect($preview['summary']['valid_rows'])->toBe(1)
        ->and($preview['summary']['invalid_rows'])->toBe(0);

    $this->actingAs($admin)
        ->post(route('admin.inventory.batch-imports.store'), [
            'preview_token' => $preview['token'],
        ])
        ->assertRedirect(route('admin.inventory.index'));

    $variant->refresh();
    $variant->load('inventoryItem');

    expect((int) $variant->inventoryItem->quantity_on_hand)->toBe(10)
        ->and(InventoryImportBatch::count())->toBe(1);

    $this->assertDatabaseHas('stock_movements', [
        'product_variant_id' => $variant->id,
        'type' => InventoryMovementType::BATCH_IMPORT,
        'quantity_delta' => 6,
        'actor_id' => $admin->id,
    ]);
});

test('batch stock import rejects files with missing required columns', function () {
    $admin = createAdminUser();
    $file = UploadedFile::fake()->createWithContent(
        'stock-import.csv',
        "sku,product_name,variant,quantity\nYSV-001,Item,Default,5\n",
    );

    $this->from(route('admin.inventory.batch-imports.create'))
        ->actingAs($admin)
        ->post(route('admin.inventory.batch-imports.preview'), [
            'file' => $file,
        ])
        ->assertRedirect(route('admin.inventory.batch-imports.create'))
        ->assertSessionHasErrors(['file']);
});

test('batch stock template downloads successfully', function () {
    $admin = createAdminUser();

    $this->actingAs($admin)
        ->get(route('admin.inventory.batch-imports.template'))
        ->assertOk()
        ->assertDownload('ysabelle-stock-import-template.csv');
});

test('walk in pos sale deducts shared inventory and creates audited order records', function () {
    $admin = createAdminUser();
    $variant = createInventoryVariant(7, ['price' => 1599]);

    $response = $this->actingAs($admin)
        ->post(route('admin.pos.store'), [
            'customer_name' => 'Walk-in Buyer',
            'customer_phone' => '09170000000',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'notes' => 'Counter sale',
            'lines_json' => json_encode([
                ['variant_id' => $variant->id, 'quantity' => 2],
            ], JSON_THROW_ON_ERROR),
        ])
        ->assertRedirect();

    $order = Order::query()->latest('id')->firstOrFail();
    $variant->refresh();
    $variant->load('inventoryItem');

    expect($order->source)->toBe('walk_in')
        ->and($order->payment_status)->toBe('paid')
        ->and($order->payment_method)->toBe('cash')
        ->and((int) $variant->inventoryItem->quantity_on_hand)->toBe(5)
        ->and($response->headers->get('Location'))->toContain(route('admin.orders.show', $order, false));

    $this->assertDatabaseHas('stock_movements', [
        'product_variant_id' => $variant->id,
        'order_id' => $order->id,
        'type' => InventoryMovementType::WALK_IN_SALE,
        'quantity_delta' => -2,
        'actor_id' => $admin->id,
    ]);
});

test('report pages and exports are available to admins with valid filters', function () {
    $admin = createAdminUser(['email' => 'reports-admin@example.com']);
    $category = Category::factory()->create(['name' => 'Reports Category']);
    $variant = createInventoryVariant(9, ['price' => 1200], ['category_id' => $category->id]);

    Order::query()->create([
        'user_id' => null,
        'source' => 'walk_in',
        'handled_by_user_id' => $admin->id,
        'order_number' => 'YSP-REPORT-001',
        'status' => 'completed',
        'payment_status' => 'paid',
        'fulfillment_status' => 'fulfilled',
        'currency' => 'PHP',
        'subtotal_amount' => 2400,
        'discount_amount' => 0,
        'shipping_amount' => 0,
        'tax_amount' => 0,
        'grand_total' => 2400,
        'placed_at' => now(),
        'notes' => 'Reporting seed',
        'customer_name' => 'Report Buyer',
        'customer_email' => null,
        'customer_phone' => null,
        'shipping_city' => null,
        'shipping_address_line' => null,
        'shipping_postal_code' => null,
        'payment_method' => 'cash',
        'metadata' => ['walk_in' => true],
    ])->items()->create([
        'product_id' => $variant->product_id,
        'product_variant_id' => $variant->id,
        'product_name' => $variant->product->name,
        'variant_name' => $variant->name,
        'sku' => $variant->sku,
        'quantity' => 2,
        'unit_price' => 1200,
        'line_total' => 2400,
        'metadata' => ['source' => 'walk_in'],
    ]);

    $this->actingAs($admin)
        ->get(route('admin.reports.index', [
            'report' => 'inventory',
            'category_id' => $category->id,
            'stock_status' => 'all',
        ]))
        ->assertOk()
        ->assertSee('Operational reports')
        ->assertSee('Inventory Report');

    $this->actingAs($admin)
        ->get(route('admin.reports.export', [
            'report' => 'walk_in_sales',
            'format' => 'csv',
        ]))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $this->actingAs($admin)
        ->get(route('admin.reports.export', [
            'report' => 'walk_in_sales',
            'format' => 'pdf',
        ]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('report filters reject invalid date ranges', function () {
    $admin = createAdminUser();

    $this->actingAs($admin)
        ->get(route('admin.reports.index', [
            'report' => 'sales',
            'date_from' => '2026-04-27',
            'date_to' => '2026-04-01',
        ]))
        ->assertSessionHasErrors(['date_to']);
});
