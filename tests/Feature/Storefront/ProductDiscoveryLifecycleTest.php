<?php

use App\Models\Access\Role;
use App\Models\Catalog\Category;
use App\Models\User;
use App\Services\Catalog\CatalogQueryService;
use App\Services\Storefront\ProductDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createProductDiscoveryAdmin(array $attributes = []): User
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

function productDiscoveryVariantPayload(array $overrides = []): array
{
    return array_replace([
        'name' => 'Size 38 / Black',
        'sku' => 'YSV-DISC-38',
        'barcode' => '1234567890123',
        'size' => '38',
        'color' => 'Black',
        'price' => '2599',
        'compare_at_price' => '2999',
        'cost_price' => '1300',
        'supplier_name' => 'Discovery Supplier',
        'weight_grams' => '700',
        'status' => 'active',
        'quantity_on_hand' => '12',
        'reorder_level' => '3',
        'allow_backorder' => '0',
    ], $overrides);
}

test('new active admin products propagate into storefront catalog and product discovery', function () {
    $admin = createProductDiscoveryAdmin();
    $category = Category::factory()->create([
        'name' => 'Discovery Runners',
        'slug' => 'discovery-runners',
        'is_active' => true,
    ]);

    $payload = [
        'category_id' => $category->id,
        'name' => 'Ivory Street',
        'slug' => 'ivory-street',
        'style_code' => 'YS-IVORY-100',
        'short_description' => 'A discovery-ready admin product.',
        'description' => 'Created through the admin builder to verify storefront propagation.',
        'primary_image_url' => 'images/products/running/aurum-runner.jpg',
        'image_alt' => 'Ivory Street product image',
        'status' => 'active',
        'is_featured' => '1',
        'force_new_badge' => '1',
        'featured_rank' => '',
        'track_inventory' => '1',
        'variants' => [
            productDiscoveryVariantPayload(),
        ],
    ];

    $response = $this->actingAs($admin)
        ->post(route('admin.catalog.products.store'), $payload);

    $response
        ->assertRedirect()
        ->assertSessionHas('toast.message', fn (string $message): bool => str_contains($message, 'Product saved.'));

    $categoryProducts = app(CatalogQueryService::class)
        ->products(['category_id' => $category->id], 12)
        ->getCollection();
    $discoveryResults = app(ProductDiscoveryService::class)->findMatches([
        'search' => 'Ivory Street',
    ])['products'];

    expect($categoryProducts->pluck('slug')->all())->toContain('ivory-street')
        ->and($discoveryResults->pluck('slug')->all())->toContain('ivory-street');
});
