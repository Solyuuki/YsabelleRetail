<?php

use App\Models\Access\Role;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Models\Storefront\VisualSearchIndexEntry;
use App\Models\User;
use App\Services\Admin\ProductCreationHealthService;
use App\Services\Storefront\VisualSearchIndexService;
use App\Support\Admin\InventoryMovementType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('storefront.assistant.ai.enabled', false);
    config()->set('storefront.assistant.visual_search.embedding.enabled', false);
});

afterEach(function (): void {
    Mockery::close();
});

function createProductHardeningAdmin(array $attributes = []): User
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

function requireProductHardeningGdSupport(): void
{
    foreach ([
        'imagecreatefromstring',
        'getimagesize',
    ] as $function) {
        if (! function_exists($function)) {
            \PHPUnit\Framework\Assert::markTestSkipped('GD image support is not available in this environment.');
        }
    }
}

function productHardeningVariantPayload(array $overrides = []): array
{
    return array_replace([
        'name' => 'Size 38 / Black',
        'sku' => 'YSV-HARD-001',
        'barcode' => '1234567890123',
        'size' => '38',
        'color' => 'Black',
        'price' => '2699',
        'compare_at_price' => '2999',
        'cost_price' => '1450',
        'supplier_name' => 'Hardening Supplier',
        'weight_grams' => '720',
        'status' => 'active',
        'quantity_on_hand' => '12',
        'reorder_level' => '3',
        'allow_backorder' => '0',
    ], $overrides);
}

function productHardeningPayload(Category $category, array $overrides = []): array
{
    return array_replace_recursive([
        'category_id' => $category->id,
        'name' => 'Hardening Runner',
        'slug' => 'hardening-runner',
        'style_code' => 'YS-HARD-100',
        'short_description' => 'A product created through the hardened admin flow.',
        'description' => 'Used to prove create-product propagation across admin, storefront, chatbot, inventory, and image search.',
        'primary_image_url' => 'images/products/running/aurum-runner.jpg',
        'image_alt' => 'Hardening Runner image',
        'status' => 'active',
        'is_featured' => '1',
        'force_new_badge' => '1',
        'remove_primary_image' => '0',
        'featured_rank' => '1',
        'track_inventory' => '1',
        'variants' => [
            productHardeningVariantPayload(),
        ],
    ], $overrides);
}

function productHardeningReadySnapshot(): array
{
    return [
        'ready' => true,
        'blocking' => [],
        'warnings' => [],
        'checks' => [],
        'blocking_message' => null,
    ];
}

test('product create success saves product variant stock movement and propagates across admin storefront chatbot and image search', function () {
    requireProductHardeningGdSupport();

    $admin = createProductHardeningAdmin();
    $category = Category::factory()->create([
        'name' => 'Hardening Running',
        'slug' => 'hardening-running',
        'is_active' => true,
    ]);

    $payload = productHardeningPayload($category);

    $this->actingAs($admin)
        ->post(route('admin.catalog.products.store'), $payload)
        ->assertRedirect();

    $product = Product::query()
        ->with(['variants.inventoryItem'])
        ->where('slug', 'hardening-runner')
        ->firstOrFail();
    $variant = $product->variants->firstOrFail();

    expect($product->category_id)->toBe($category->id)
        ->and($product->status)->toBe('active')
        ->and((string) $product->primary_image_url)->toBe('images/products/running/aurum-runner.jpg')
        ->and((bool) $product->track_inventory)->toBeTrue()
        ->and((float) $product->base_price)->toBe(2699.0)
        ->and((float) $variant->price)->toBe(2699.0)
        ->and((int) $variant->inventoryItem->quantity_on_hand)->toBe(12)
        ->and((int) $variant->inventoryItem->reorder_level)->toBe(3)
        ->and((bool) $variant->inventoryItem->allow_backorder)->toBeFalse();

    $this->assertDatabaseHas('stock_movements', [
        'product_variant_id' => $variant->id,
        'type' => InventoryMovementType::STOCK_IN,
        'quantity_delta' => 12,
        'actor_id' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.catalog.products.index', ['search' => 'Hardening Runner']))
        ->assertOk()
        ->assertSee('Hardening Runner')
        ->assertSee('YS-HARD-100');

    $this->actingAs($admin)
        ->get(route('admin.inventory.index', ['search' => 'YSV-HARD-001']))
        ->assertOk()
        ->assertSee('Hardening Runner')
        ->assertSee('YSV-HARD-001');

    $this->get(route('storefront.shop'))
        ->assertOk()
        ->assertSee('Hardening Runner');

    $this->get(route('storefront.shop', ['category' => $category->slug]))
        ->assertOk()
        ->assertSee('Hardening Runner');

    $this->get(route('storefront.shop', ['search' => 'Hardening Runner']))
        ->assertOk()
        ->assertSee('Hardening Runner');

    $this->get(route('storefront.shop', ['search' => 'YSV-HARD-001']))
        ->assertOk()
        ->assertSee('Hardening Runner');

    $this->postJson(route('storefront.assistant.message'), [
        'message' => 'Do you have Hardening Runner?',
    ])
        ->assertOk()
        ->assertJsonPath('products.0.name', 'Hardening Runner')
        ->assertJsonPath('products.0.slug', 'hardening-runner');

    $visualEntry = VisualSearchIndexEntry::query()
        ->where('product_id', $product->id)
        ->first();

    expect($visualEntry)->not->toBeNull()
        ->and($visualEntry?->image_role)->toBe('primary');
});

test('schema readiness blocks product create with a friendly admin error when required product columns are missing', function () {
    $admin = createProductHardeningAdmin();
    $category = Category::factory()->create(['is_active' => true]);

    Schema::table('products', function (Blueprint $table): void {
        $table->dropColumn('force_new_badge');
    });

    $this->from(route('admin.catalog.products.create'))
        ->actingAs($admin)
        ->post(route('admin.catalog.products.store'), productHardeningPayload($category))
        ->assertRedirect(route('admin.catalog.products.create'))
        ->assertSessionHas('toast.title', 'Product create unavailable')
        ->assertSessionHas('toast.message', 'Product creation is blocked until the latest catalog migrations are applied.');

    expect(Product::query()->where('slug', 'hardening-runner')->exists())->toBeFalse();
});

test('storage failure returns a friendly admin error and rolls back partial product writes', function () {
    $admin = createProductHardeningAdmin();
    $category = Category::factory()->create(['is_active' => true]);
    $health = Mockery::mock(ProductCreationHealthService::class);
    $health->shouldReceive('snapshot')
        ->once()
        ->andReturn(productHardeningReadySnapshot());
    $this->app->instance(ProductCreationHealthService::class, $health);

    Storage::shouldReceive('disk')
        ->once()
        ->with('public')
        ->andReturnSelf();
    Storage::shouldReceive('putFileAs')
        ->once()
        ->andThrow(new RuntimeException('storage unavailable'));

    $payload = productHardeningPayload($category, [
        'primary_image_url' => '',
        'primary_image_upload' => UploadedFile::fake()->image('hardening-runner.jpg'),
    ]);

    $this->from(route('admin.catalog.products.create'))
        ->actingAs($admin)
        ->post(route('admin.catalog.products.store'), $payload)
        ->assertRedirect(route('admin.catalog.products.create'))
        ->assertSessionHas('toast.title', 'Product not saved')
        ->assertSessionHas('toast.message', 'Product image storage is temporarily unavailable. Please restore public storage access and try again.');

    expect(Product::query()->where('slug', 'hardening-runner')->exists())->toBeFalse()
        ->and(ProductVariant::query()->where('sku', 'YSV-HARD-001')->exists())->toBeFalse()
        ->and(VisualSearchIndexEntry::query()->count())->toBe(0);

    $this->assertDatabaseCount('inventory_items', 0);
    $this->assertDatabaseCount('stock_movements', 0);
});

test('invalid product data returns validation errors', function () {
    $admin = createProductHardeningAdmin();

    $this->from(route('admin.catalog.products.create'))
        ->actingAs($admin)
        ->post(route('admin.catalog.products.store'), [
            'category_id' => null,
            'name' => '',
            'slug' => '',
            'style_code' => '',
            'short_description' => '',
            'description' => '',
            'primary_image_url' => 'not-a-valid-image-path',
            'image_alt' => '',
            'status' => 'active',
            'is_featured' => '0',
            'force_new_badge' => '0',
            'remove_primary_image' => '0',
            'featured_rank' => '',
            'track_inventory' => '1',
            'variants' => [],
        ])
        ->assertRedirect(route('admin.catalog.products.create'))
        ->assertSessionHasErrors([
            'category_id',
            'name',
            'variants',
        ]);
});

test('image sync failures do not roll back a successful product create and warn the admin instead', function () {
    $admin = createProductHardeningAdmin();
    $category = Category::factory()->create([
        'name' => 'Warning Runners',
        'slug' => 'warning-runners',
        'is_active' => true,
    ]);
    $visualSearch = Mockery::mock(VisualSearchIndexService::class);
    $visualSearch->shouldReceive('syncProduct')
        ->once()
        ->andThrow(new RuntimeException('visual search unavailable'));
    $this->app->instance(VisualSearchIndexService::class, $visualSearch);

    $this->actingAs($admin)
        ->post(route('admin.catalog.products.store'), productHardeningPayload($category, [
            'name' => 'Warning Runner',
            'slug' => 'warning-runner',
            'style_code' => 'YS-HARD-200',
            'variants' => [
                productHardeningVariantPayload([
                    'sku' => 'YSV-HARD-200',
                ]),
            ],
        ]))
        ->assertRedirect()
        ->assertSessionHas('toast.message', fn (string $message): bool => str_contains($message, 'image search sync is pending or failed'));

    expect(Product::query()->where('slug', 'warning-runner')->exists())->toBeTrue();
});
