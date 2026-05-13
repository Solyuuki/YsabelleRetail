<?php

use App\Models\Access\Role;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Models\User;
use App\Support\Storefront\ProductMediaResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createProductUploadAdmin(array $attributes = []): User
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

function fakeCatalogUpload(string $name = 'catalog-image.png'): UploadedFile
{
    return UploadedFile::fake()->image($name, 900, 900);
}

function productVariantPayload(array $overrides = []): array
{
    return array_replace([
        'name' => 'Size 38 / Black',
        'sku' => 'YSV-UPL-001',
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
    ], $overrides);
}

function productFormPayload(Category $category, array $overrides = [], array $variantOverrides = []): array
{
    return array_replace_recursive([
        'category_id' => $category->id,
        'name' => 'Uploaded Catalog Runner',
        'slug' => 'uploaded-catalog-runner',
        'style_code' => 'YS-UPLOAD-1001',
        'short_description' => 'Catalog upload test record.',
        'description' => 'Used to verify admin product image upload handling.',
        'primary_image_url' => '',
        'image_alt' => 'Uploaded Catalog Runner image',
        'status' => 'active',
        'is_featured' => '0',
        'featured_rank' => '',
        'track_inventory' => '1',
        'variants' => [
            productVariantPayload($variantOverrides),
        ],
    ], $overrides);
}

function createProductWithVariant(array $productOverrides = [], array $variantOverrides = []): Product
{
    $product = Product::factory()->create($productOverrides);
    $variant = ProductVariant::factory()
        ->for($product)
        ->create(array_replace([
            'name' => 'Size 38 / Black',
            'sku' => 'YSV-UPL-EXISTING',
            'option_values' => ['size' => '38', 'color' => 'Black'],
            'price' => 2499,
            'status' => 'active',
        ], $variantOverrides));

    $variant->inventoryItem()->create([
        'quantity_on_hand' => 8,
        'reserved_quantity' => 0,
        'reorder_level' => 2,
        'allow_backorder' => false,
    ]);

    return $product->fresh(['variants.inventoryItem']);
}

test('admin product create stores an uploaded primary image on the public disk', function () {
    Storage::fake('public');

    $admin = createProductUploadAdmin();
    $category = Category::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.catalog.products.store'), productFormPayload($category, [
            'primary_image_upload' => fakeCatalogUpload('hero-shot.png'),
        ]))
        ->assertRedirect()
        ->assertSessionHas('toast.message', fn (string $message): bool => str_contains($message, 'Rebuild visual search index to include this image.'));

    $product = Product::query()->where('slug', 'uploaded-catalog-runner')->firstOrFail();
    $storedRelativePath = Str::after((string) $product->primary_image_url, 'storage/');

    expect($product->primary_image_url)
        ->toStartWith('storage/products/'.$product->id.'/primary-')
        ->and($product->primary_image_updated_at)->not->toBeNull();

    Storage::disk('public')->assertExists($storedRelativePath);
});

test('admin product edit replaces the primary image with an uploaded file and gives upload priority over the fallback path', function () {
    Storage::fake('public');

    $admin = createProductUploadAdmin();
    $product = createProductWithVariant([
        'primary_image_url' => 'https://example.com/original-runner.jpg',
    ]);
    $variant = $product->variants->firstOrFail();

    $payload = productFormPayload($product->category, [
        'name' => $product->name,
        'slug' => $product->slug,
        'style_code' => $product->style_code,
        'primary_image_url' => 'images/products/running/ignored-fallback.jpg',
        'primary_image_upload' => fakeCatalogUpload('replacement.webp'),
        'image_alt' => 'Replacement image',
    ], [
        'id' => $variant->id,
        'name' => $variant->name,
        'sku' => $variant->sku,
        'barcode' => $variant->barcode ?? '',
        'size' => $variant->option_values['size'] ?? '',
        'color' => $variant->option_values['color'] ?? '',
        'price' => (string) $variant->price,
        'compare_at_price' => $variant->compare_at_price !== null ? (string) $variant->compare_at_price : '',
        'cost_price' => $variant->cost_price !== null ? (string) $variant->cost_price : '',
        'supplier_name' => $variant->supplier_name ?? '',
        'weight_grams' => $variant->weight_grams !== null ? (string) $variant->weight_grams : '',
        'status' => $variant->status,
        'quantity_on_hand' => (string) ($variant->inventoryItem?->quantity_on_hand ?? 0),
        'reorder_level' => (string) ($variant->inventoryItem?->reorder_level ?? 0),
        'allow_backorder' => ($variant->inventoryItem?->allow_backorder ?? false) ? '1' : '0',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.catalog.products.update', $product), $payload)
        ->assertRedirect()
        ->assertSessionHas('toast.message', fn (string $message): bool => str_contains($message, 'Rebuild visual search index to include this image.'));

    $product->refresh();

    expect($product->primary_image_url)
        ->toStartWith('storage/products/'.$product->id.'/primary-')
        ->not->toBe('images/products/running/ignored-fallback.jpg')
        ->not->toBe('https://example.com/original-runner.jpg');
});

test('admin product create rejects invalid primary image upload types', function () {
    Storage::fake('public');

    $admin = createProductUploadAdmin();
    $category = Category::factory()->create();

    $this->from(route('admin.catalog.products.create'))
        ->actingAs($admin)
        ->post(route('admin.catalog.products.store'), productFormPayload($category, [
            'primary_image_upload' => UploadedFile::fake()->create('bad-upload.pdf', 64, 'application/pdf'),
        ]))
        ->assertRedirect(route('admin.catalog.products.create'))
        ->assertSessionHasErrors(['primary_image_upload']);

    expect(Product::query()->count())->toBe(0);
});

test('admin product create still accepts the existing primary image path fallback', function () {
    $admin = createProductUploadAdmin();
    $category = Category::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.catalog.products.store'), productFormPayload($category, [
            'primary_image_url' => 'images/products/running/aurum-runner.jpg',
        ]))
        ->assertRedirect();

    $product = Product::query()->where('slug', 'uploaded-catalog-runner')->firstOrFail();

    expect($product->primary_image_url)->toBe('images/products/running/aurum-runner.jpg');
});

test('admin product edit can remove the existing primary image safely', function () {
    $admin = createProductUploadAdmin();
    $product = createProductWithVariant([
        'primary_image_url' => 'images/products/running/aurum-runner.jpg',
    ]);
    $variant = $product->variants->firstOrFail();

    $payload = productFormPayload($product->category, [
        'name' => $product->name,
        'slug' => $product->slug,
        'style_code' => $product->style_code,
        'primary_image_url' => '',
        'remove_primary_image' => '1',
    ], [
        'id' => $variant->id,
        'name' => $variant->name,
        'sku' => $variant->sku,
        'barcode' => $variant->barcode ?? '',
        'size' => $variant->option_values['size'] ?? '',
        'color' => $variant->option_values['color'] ?? '',
        'price' => (string) $variant->price,
        'compare_at_price' => $variant->compare_at_price !== null ? (string) $variant->compare_at_price : '',
        'cost_price' => $variant->cost_price !== null ? (string) $variant->cost_price : '',
        'supplier_name' => $variant->supplier_name ?? '',
        'weight_grams' => $variant->weight_grams !== null ? (string) $variant->weight_grams : '',
        'status' => $variant->status,
        'quantity_on_hand' => (string) ($variant->inventoryItem?->quantity_on_hand ?? 0),
        'reorder_level' => (string) ($variant->inventoryItem?->reorder_level ?? 0),
        'allow_backorder' => ($variant->inventoryItem?->allow_backorder ?? false) ? '1' : '0',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.catalog.products.update', $product), $payload)
        ->assertRedirect()
        ->assertSessionHas('toast.message', fn (string $message): bool => str_contains($message, 'Rebuild visual search index to include this image.'));

    expect($product->fresh()->primary_image_url)->toBeNull();
});

test('uploaded product images resolve through the product media resolver', function () {
    Storage::fake('public');

    $admin = createProductUploadAdmin();
    $category = Category::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.catalog.products.store'), productFormPayload($category, [
            'primary_image_upload' => fakeCatalogUpload('resolver-check.jpg'),
        ]))
        ->assertRedirect();

    $product = Product::query()->where('slug', 'uploaded-catalog-runner')->firstOrFail();
    $media = app(ProductMediaResolver::class);

    expect($media->imageUrlFor($product))->toBe(asset($product->primary_image_url))
        ->and($media->pathFor($product))->toBe($product->primary_image_url);
});
