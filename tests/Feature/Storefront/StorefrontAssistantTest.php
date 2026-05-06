<?php

use App\Models\Cart\Cart;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Models\Storefront\VisualSearchIndexEntry;
use App\Models\User;
use App\Services\Storefront\VisualProductSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('storefront.assistant.ai.enabled', false);
});

function makeStorefrontProduct(array $overrides = [], array $variantOverrides = [], array $inventoryOverrides = []): Product
{
    $categorySlug = $overrides['category_slug'] ?? 'running';
    $categoryName = $overrides['category_name'] ?? 'Running';

    $category = Category::query()->firstOrCreate(
        ['slug' => $categorySlug],
        [
            'name' => $categoryName,
            'description' => fake()->sentence(),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 25),
        ],
    );

    $product = Product::factory()->for($category)->create(array_merge([
        'name' => 'Night Runner',
        'slug' => 'night-runner',
        'style_code' => 'YS-'.strtoupper(fake()->unique()->lexify('???')).'-'.fake()->unique()->numerify('####'),
        'short_description' => 'Black performance runner for everyday miles.',
        'description' => 'A black running shoe with stable cushioning and sleek support.',
        'base_price' => 5990,
        'status' => 'active',
    ], collect($overrides)->except(['category_name', 'category_slug'])->all()));

    $variant = ProductVariant::factory()->for($product)->create(array_merge([
        'name' => 'Size 9',
        'sku' => 'YS-NGT-6000-9',
        'option_values' => [
            'size' => '9',
            'color' => 'Black',
        ],
        'price' => $product->base_price,
        'status' => 'active',
    ], $variantOverrides));

    $variant->inventoryItem()->create(array_merge([
        'quantity_on_hand' => 8,
        'reserved_quantity' => 0,
        'reorder_level' => 2,
        'allow_backorder' => false,
    ], $inventoryOverrides));

    return $product->fresh(['category', 'variants.inventoryItem']);
}

function visualSearchFixturePath(string $filename): string
{
    $directory = public_path('testing/visual-search');

    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    return $directory.DIRECTORY_SEPARATOR.$filename;
}

function visualSearchFixtureUrl(string $filename): string
{
    return url('testing/visual-search/'.$filename);
}

function drawShoeFixture(string $filename, string $upperHex, string $soleHex = '#202020', bool $stripe = true): string
{
    $path = visualSearchFixturePath($filename);
    $image = imagecreatetruecolor(240, 140);
    $white = allocateHexColor($image, '#ffffff');
    $upper = allocateHexColor($image, $upperHex);
    $sole = allocateHexColor($image, $soleHex);
    $stripeColor = allocateHexColor($image, '#f6f1df');

    imagefill($image, 0, 0, $white);
    imagefilledpolygon($image, [
        26, 88,
        70, 56,
        120, 52,
        165, 64,
        196, 78,
        204, 88,
        168, 92,
        138, 102,
        72, 102,
        36, 96,
    ], 10, $upper);
    imagefilledrectangle($image, 34, 95, 206, 108, $sole);
    imagefilledellipse($image, 190, 86, 34, 22, $upper);

    if ($stripe) {
        imagefilledpolygon($image, [
            88, 66,
            110, 60,
            144, 72,
            140, 78,
            108, 70,
            92, 74,
        ], 6, $stripeColor);
    }

    imagesetthickness($image, 3);
    imageline($image, 60, 94, 80, 70, $sole);
    imageline($image, 80, 94, 100, 68, $sole);
    imageline($image, 100, 94, 122, 70, $sole);
    imageline($image, 120, 94, 142, 76, $sole);

    imagepng($image, $path);
    imagedestroy($image);

    return $path;
}

function drawObjectFixture(string $filename, string $fillHex): string
{
    $path = visualSearchFixturePath($filename);
    $image = imagecreatetruecolor(240, 140);
    $white = allocateHexColor($image, '#ffffff');
    $fill = allocateHexColor($image, $fillHex);
    $accent = allocateHexColor($image, '#f2a444');

    imagefill($image, 0, 0, $white);
    imagefilledellipse($image, 120, 70, 84, 84, $fill);
    imagefilledellipse($image, 120, 70, 28, 28, $accent);

    imagepng($image, $path);
    imagedestroy($image);

    return $path;
}

function createCroppedFixture(string $sourceFilename, string $targetFilename): string
{
    $sourcePath = visualSearchFixturePath($sourceFilename);
    $targetPath = visualSearchFixturePath($targetFilename);
    $source = imagecreatefrompng($sourcePath);
    $cropped = imagecrop($source, [
        'x' => 44,
        'y' => 40,
        'width' => 150,
        'height' => 72,
    ]);

    imagepng($cropped, $targetPath);
    imagedestroy($cropped);
    imagedestroy($source);

    return $targetPath;
}

function createScreenshotFixture(string $sourceFilename, string $targetFilename): string
{
    $sourcePath = is_file($sourceFilename) ? $sourceFilename : visualSearchFixturePath($sourceFilename);
    $targetPath = visualSearchFixturePath($targetFilename);
    $source = imagecreatefromstring(file_get_contents($sourcePath));
    $canvas = imagecreatetruecolor(420, 280);
    $bg = allocateHexColor($canvas, '#f5f3ee');
    $chrome = allocateHexColor($canvas, '#d9d5cd');
    $frame = allocateHexColor($canvas, '#ffffff');
    $shadow = allocateHexColor($canvas, '#c5beaf');

    imagefill($canvas, 0, 0, $bg);
    imagefilledrectangle($canvas, 24, 18, 396, 42, $chrome);
    imagefilledrectangle($canvas, 44, 62, 374, 238, $frame);
    imagerectangle($canvas, 44, 62, 374, 238, $shadow);
    imagecopyresampled($canvas, $source, 74, 88, 0, 0, 270, 158, imagesx($source), imagesy($source));

    imagejpeg($canvas, $targetPath, 82);
    imagedestroy($canvas);
    imagedestroy($source);

    return $targetPath;
}

function createBlurredFixture(string $sourceFilename, string $targetFilename): string
{
    $sourcePath = visualSearchFixturePath($sourceFilename);
    $targetPath = visualSearchFixturePath($targetFilename);
    $source = imagecreatefrompng($sourcePath);
    $canvas = imagecreatetruecolor(imagesx($source), imagesy($source));
    imagecopy($canvas, $source, 0, 0, 0, 0, imagesx($source), imagesy($source));

    for ($index = 0; $index < 4; $index++) {
        imagefilter($canvas, IMG_FILTER_GAUSSIAN_BLUR);
    }

    imagejpeg($canvas, $targetPath, 70);
    imagedestroy($canvas);
    imagedestroy($source);

    return $targetPath;
}

function allocateHexColor(GdImage $image, string $hex): int
{
    $hex = ltrim($hex, '#');

    return imagecolorallocate(
        $image,
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
    );
}

function uploadFromFixture(string $path, string $name): UploadedFile
{
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mimeType = match ($extension) {
        'jpg', 'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        default => 'image/png',
    };

    return new UploadedFile($path, $name, $mimeType, null, true);
}

function assistantCsrfHeaders(array $headers = []): array
{
    return array_merge([
        'Accept' => 'application/json',
        'X-CSRF-TOKEN' => 'assistant-test-token',
        'X-Requested-With' => 'XMLHttpRequest',
    ], $headers);
}

function assistantPostJson($test, string $route, array $payload = [], array $headers = [])
{
    return $test
        ->withSession(['_token' => 'assistant-test-token'])
        ->postJson($route, $payload, assistantCsrfHeaders($headers));
}

function assistantPost($test, string $route, array $payload = [], array $headers = [])
{
    return $test
        ->withSession(['_token' => 'assistant-test-token'])
        ->post($route, $payload, assistantCsrfHeaders($headers));
}

test('assistant returns product matches for running shoe questions', function () {
    $product = makeStorefrontProduct();

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'I need running shoes',
    ])
        ->assertOk()
        ->assertJsonPath('products.0.slug', $product->slug)
        ->assertJsonPath('products.0.availability.state', 'in_stock');
});

test('assistant treats greetings as conversational and returns no products', function () {
    makeStorefrontProduct();

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'Hello',
    ])
        ->assertOk()
        ->assertJsonPath('answer', 'Welcome to Ysabelle Retail. I can help you find the right pair, check stock, review your cart, or match a shoe photo from the current catalog.')
        ->assertJsonCount(0, 'products');
});

test('assistant keeps small talk domain-bounded and returns no products', function () {
    makeStorefrontProduct();

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'Thanks',
    ])
        ->assertOk()
        ->assertJsonPath('answer', 'You are very welcome. If you want, I can keep helping with products, sizing, stock, or a similar-by-image search.')
        ->assertJsonCount(0, 'products');
});

test('assistant returns cart guidance from the active cart', function () {
    $user = User::factory()->create();
    $product = makeStorefrontProduct();
    $variant = $product->variants->firstOrFail();

    $cart = Cart::query()->create([
        'user_id' => $user->id,
        'status' => 'active',
        'currency' => 'PHP',
        'expires_at' => now()->addDays(7),
    ]);

    $cart->items()->create([
        'product_variant_id' => $variant->id,
        'quantity' => 2,
        'unit_price' => 5990,
        'line_total' => 11980,
        'metadata' => ['product_slug' => $product->slug],
    ]);

    $this->actingAs($user)
        ->withSession(['_token' => 'assistant-test-token'])
        ->postJson(route('storefront.assistant.message'), [
            'message' => 'What is in my cart?',
        ], assistantCsrfHeaders())
        ->assertOk()
        ->assertJsonPath('products.0.slug', $product->slug)
        ->assertJsonPath('actions.0.label', 'View cart');
});

test('assistant redirects out of scope questions back to storefront help', function () {
    makeStorefrontProduct();

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'What is the capital of France?',
    ])
        ->assertOk()
        ->assertJsonPath('answer', 'I can only help with Ysabelle Retail shopping support, such as products, stock, sizing, cart, checkout, and catalog image search.')
        ->assertJsonCount(0, 'products');
});

test('assistant asks for clarification when the request is unclear', function () {
    makeStorefrontProduct();

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'Maybe',
    ])
        ->assertOk()
        ->assertJsonPath('answer', 'I can help with shoe recommendations, stock, sizing, cart, checkout, or image search. Tell me your preferred color, budget, size, or use case and I will guide you from there.')
        ->assertJsonCount(0, 'products');
});

test('assistant falls back safely when ollama is unavailable', function () {
    makeStorefrontProduct();

    config()->set('storefront.assistant.ai.enabled', true);
    config()->set('storefront.assistant.ai.provider', 'ollama');
    config()->set('storefront.assistant.ai.ollama.model', 'llama3.2:3b');

    Http::fake([
        'http://127.0.0.1:11434/api/generate' => Http::response(['error' => 'offline'], 503),
    ]);

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'Hello',
    ])
        ->assertOk()
        ->assertJsonPath('answer', 'Welcome to Ysabelle Retail. I can help you find the right pair, check stock, review your cart, or match a shoe photo from the current catalog.')
        ->assertJsonCount(0, 'products');
});

test('assistant stream route returns event stream payload without breaking chat responses', function () {
    makeStorefrontProduct();

    $response = assistantPost($this, route('storefront.assistant.message.stream'), [
        'message' => 'Hello',
    ], [
        'Accept' => 'text/event-stream',
    ]);

    $response->assertOk();
    expect((string) $response->headers->get('content-type'))->toContain('text/event-stream');

    $stream = $response->streamedContent();

    expect($stream)
        ->toContain('event: chunk')
        ->toContain('event: done')
        ->toContain('Welcome to Ysabelle Retail. I can help you find the right pair, check stock, review your cart, or match a shoe photo from the current catalog.');
});

test('visual search returns similar products from local hints', function () {
    drawShoeFixture('night-runner-product.png', '#1f1f1f');
    $product = makeStorefrontProduct([
        'primary_image_url' => visualSearchFixtureUrl('night-runner-product.png'),
        'image_alt' => 'Night Runner product image',
    ]);

    $this->artisan('visual-search:index', ['--fresh' => true])->assertExitCode(0);

    assistantPost($this, route('storefront.assistant.visual-search'), [
        'image' => uploadFromFixture(visualSearchFixturePath('night-runner-product.png'), 'black-runner-query.png'),
        'category' => 'running',
        'color' => 'black',
    ], [
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJsonPath('products.0.slug', $product->slug)
        ->assertJsonPath('products.0.match.confidence', 'strong_match')
        ->assertJsonPath('match.engine', 'embedding');
});

test('visual search rejects invalid file types', function () {
    assistantPost($this, route('storefront.assistant.visual-search'), [
        'image' => UploadedFile::fake()->create('notes.pdf', 64, 'application/pdf'),
    ], [
        'Accept' => 'application/json',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['image']);
});

test('visual search requires an uploaded image', function () {
    assistantPost($this, route('storefront.assistant.visual-search'), [
        'category' => 'running',
        'color' => 'black',
    ], [
        'Accept' => 'application/json',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['image']);
});

test('visual search returns fallback recommendations for unrelated images', function () {
    drawShoeFixture('catalog-blue-runner.png', '#2d61d2');
    drawShoeFixture('catalog-black-runner.png', '#232323');

    makeStorefrontProduct([
        'name' => 'Blue Runner',
        'slug' => 'blue-runner',
        'primary_image_url' => visualSearchFixtureUrl('catalog-blue-runner.png'),
        'image_alt' => 'Blue Runner product image',
    ], [
        'sku' => 'YS-BLU-6200-9',
        'option_values' => [
            'size' => '9',
            'color' => 'Blue',
        ],
    ]);

    makeStorefrontProduct([
        'name' => 'Black Runner',
        'slug' => 'black-runner',
        'primary_image_url' => visualSearchFixtureUrl('catalog-black-runner.png'),
        'image_alt' => 'Black Runner product image',
    ], [
        'sku' => 'YS-BLK-6200-9',
        'option_values' => [
            'size' => '9',
            'color' => 'Black',
        ],
    ]);

    drawObjectFixture('orange-object.png', '#ef7c28');
    $this->artisan('visual-search:index', ['--fresh' => true])->assertExitCode(0);

    assistantPost($this, route('storefront.assistant.visual-search'), [
        'image' => uploadFromFixture(visualSearchFixturePath('orange-object.png'), 'orange-object.png'),
    ], [
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJsonPath('match.confidence', 'fallback_recommendation')
        ->assertJsonPath('match.reason', 'non_shoe')
        ->assertJsonPath('products.0.match.confidence', 'fallback_recommendation')
        ->assertJsonCount(2, 'products');
});

test('visual search does not let metadata hints override stronger visual similarity', function () {
    drawShoeFixture('catalog-blue-shoe.png', '#255fd4');
    drawShoeFixture('catalog-black-shoe.png', '#1e1e1e');

    $blueProduct = makeStorefrontProduct([
        'name' => 'Azure Tempo',
        'slug' => 'azure-tempo',
        'primary_image_url' => visualSearchFixtureUrl('catalog-blue-shoe.png'),
        'image_alt' => 'Azure Tempo product image',
    ], [
        'sku' => 'YS-AZR-6100-9',
        'option_values' => [
            'size' => '9',
            'color' => 'Blue',
        ],
    ]);

    makeStorefrontProduct([
        'name' => 'Shadow Tempo',
        'slug' => 'shadow-tempo',
        'primary_image_url' => visualSearchFixtureUrl('catalog-black-shoe.png'),
        'image_alt' => 'Shadow Tempo product image',
    ], [
        'sku' => 'YS-SHD-6100-9',
        'option_values' => [
            'size' => '9',
            'color' => 'Black',
        ],
    ]);

    $this->artisan('visual-search:index', ['--fresh' => true])->assertExitCode(0);

    assistantPost($this, route('storefront.assistant.visual-search'), [
        'image' => uploadFromFixture(visualSearchFixturePath('catalog-blue-shoe.png'), 'query-blue-shoe.png'),
        'color' => 'black',
        'category' => 'running',
    ], [
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJsonPath('products.0.slug', $blueProduct->slug);
});

test('visual search uses metadata hints to make fallback recommendations useful', function () {
    drawShoeFixture('catalog-gold-shoe.png', '#b68f2a');
    makeStorefrontProduct([
        'name' => 'Aurum Runner',
        'slug' => 'aurum-runner',
        'primary_image_url' => visualSearchFixtureUrl('catalog-gold-shoe.png'),
        'image_alt' => 'Aurum Runner product image',
    ], [
        'sku' => 'YS-AUR-6100-9',
        'option_values' => [
            'size' => '9',
            'color' => 'Gold',
        ],
    ]);

    drawObjectFixture('green-object.png', '#2f9f61');
    $this->artisan('visual-search:index', ['--fresh' => true])->assertExitCode(0);

    assistantPost($this, route('storefront.assistant.visual-search'), [
        'image' => uploadFromFixture(visualSearchFixturePath('green-object.png'), 'green-object.png'),
        'category' => 'running',
        'color' => 'gold',
        'use_case' => 'running',
    ], [
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJsonPath('match.confidence', 'fallback_recommendation')
        ->assertJsonPath('products.0.slug', 'aurum-runner');
});

test('visual search matches cropped uploads for the same product', function () {
    drawShoeFixture('crop-source-shoe.png', '#2d61d2');
    createCroppedFixture('crop-source-shoe.png', 'crop-query-shoe.png');
    $product = makeStorefrontProduct([
        'name' => 'Crop Runner',
        'slug' => 'crop-runner',
        'primary_image_url' => visualSearchFixtureUrl('crop-source-shoe.png'),
        'image_alt' => 'Crop Runner product image',
    ]);

    $this->artisan('visual-search:index', ['--fresh' => true])->assertExitCode(0);

    assistantPost($this, route('storefront.assistant.visual-search'), [
        'image' => uploadFromFixture(visualSearchFixturePath('crop-query-shoe.png'), 'crop-query-shoe.png'),
    ], [
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJsonPath('products.0.slug', $product->slug)
        ->assertJsonPath('match.engine', 'embedding');
});

test('visual search matches screenshot style uploads for the same product', function () {
    $sourcePath = public_path('images/products/running/aurum-runner.jpg');
    createScreenshotFixture($sourcePath, 'screenshot-query-shoe.jpg');
    $product = makeStorefrontProduct([
        'name' => 'Screen Runner',
        'slug' => 'screen-runner',
        'primary_image_url' => url('images/products/running/aurum-runner.jpg'),
        'image_alt' => 'Screen Runner product image',
    ]);

    $this->artisan('visual-search:index', ['--fresh' => true])->assertExitCode(0);

    assistantPost($this, route('storefront.assistant.visual-search'), [
        'image' => uploadFromFixture(visualSearchFixturePath('screenshot-query-shoe.jpg'), 'screenshot-query-shoe.jpg'),
    ], [
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJsonPath('products.0.slug', $product->slug)
        ->assertJsonPath('match.engine', 'embedding');
});

test('visual search returns one representative for unrelated products sharing the same image', function () {
    $sharedImageUrl = url('images/products/running/aurum-runner.jpg');
    $sharedImagePath = public_path('images/products/running/aurum-runner.jpg');

    $primary = makeStorefrontProduct([
        'name' => 'Cluster Runner',
        'slug' => 'cluster-runner',
        'primary_image_url' => $sharedImageUrl,
        'image_alt' => 'Cluster Runner product image',
    ], [
        'sku' => 'YS-CLR-6100-9',
    ]);

    $duplicate = makeStorefrontProduct([
        'name' => 'Cluster Street',
        'slug' => 'cluster-street',
        'category_name' => 'Sneakers',
        'category_slug' => 'sneakers',
        'primary_image_url' => $sharedImageUrl,
        'image_alt' => 'Cluster Street product image',
    ], [
        'sku' => 'YS-CLS-6100-9',
    ]);

    $this->artisan('visual-search:index', ['--fresh' => true])->assertExitCode(0);

    $response = assistantPost($this, route('storefront.assistant.visual-search'), [
        'image' => uploadFromFixture($sharedImagePath, 'shared-cluster-shoe.jpg'),
    ], [
        'Accept' => 'application/json',
    ])->assertOk();

    $slugs = collect($response->json('products'))->pluck('slug');

    expect($slugs->intersect([$primary->slug, $duplicate->slug])->count())->toBe(1);
});

test('visual search keeps duplicate image clusters from dominating the final results', function () {
    drawShoeFixture('shared-duplicate-cluster.png', '#222222');
    drawShoeFixture('unique-query-runner.png', '#2d61d2');
    drawShoeFixture('secondary-result-shoe.png', '#b68f2a');

    $exact = makeStorefrontProduct([
        'name' => 'Azure Signal',
        'slug' => 'azure-signal',
        'primary_image_url' => visualSearchFixtureUrl('unique-query-runner.png'),
        'image_alt' => 'Azure Signal product image',
    ], [
        'sku' => 'YS-AZS-6200-9',
        'option_values' => [
            'size' => '9',
            'color' => 'Blue',
        ],
    ]);

    makeStorefrontProduct([
        'name' => 'Duplicate One',
        'slug' => 'duplicate-one',
        'primary_image_url' => visualSearchFixtureUrl('shared-duplicate-cluster.png'),
        'image_alt' => 'Duplicate One product image',
    ], [
        'sku' => 'YS-DU1-6200-9',
    ]);

    makeStorefrontProduct([
        'name' => 'Duplicate Two',
        'slug' => 'duplicate-two',
        'category_name' => 'Sneakers',
        'category_slug' => 'sneakers',
        'primary_image_url' => visualSearchFixtureUrl('shared-duplicate-cluster.png'),
        'image_alt' => 'Duplicate Two product image',
    ], [
        'sku' => 'YS-DU2-6200-9',
    ]);

    makeStorefrontProduct([
        'name' => 'Amber Horizon',
        'slug' => 'amber-horizon',
        'primary_image_url' => visualSearchFixtureUrl('secondary-result-shoe.png'),
        'image_alt' => 'Amber Horizon product image',
    ], [
        'sku' => 'YS-AMH-6200-9',
        'option_values' => [
            'size' => '9',
            'color' => 'Gold',
        ],
    ]);

    $this->artisan('visual-search:index', ['--fresh' => true])->assertExitCode(0);

    $response = assistantPost($this, route('storefront.assistant.visual-search'), [
        'image' => uploadFromFixture(visualSearchFixturePath('unique-query-runner.png'), 'unique-query-runner.png'),
        'category' => 'running',
        'color' => 'blue',
    ], [
        'Accept' => 'application/json',
    ])->assertOk()
        ->assertJsonPath('products.0.slug', $exact->slug);

    $clusterSlugs = collect($response->json('products'))
        ->pluck('slug')
        ->filter(fn (string $slug): bool => in_array($slug, ['duplicate-one', 'duplicate-two'], true));

    expect($clusterSlugs->count())->toBeLessThanOrEqual(1);
});

test('visual search handles moderately blurry uploads without random fallback', function () {
    drawShoeFixture('blur-source-shoe.png', '#b68f2a');
    createBlurredFixture('blur-source-shoe.png', 'blur-query-shoe.jpg');
    $product = makeStorefrontProduct([
        'name' => 'Blur Runner',
        'slug' => 'blur-runner',
        'primary_image_url' => visualSearchFixtureUrl('blur-source-shoe.png'),
        'image_alt' => 'Blur Runner product image',
    ]);

    $this->artisan('visual-search:index', ['--fresh' => true])->assertExitCode(0);

    assistantPost($this, route('storefront.assistant.visual-search'), [
        'image' => uploadFromFixture(visualSearchFixturePath('blur-query-shoe.jpg'), 'blur-query-shoe.jpg'),
    ], [
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJsonPath('products.0.slug', $product->slug);
});

test('visual search scores in the candidate band become approximate matches', function () {
    $service = app(VisualProductSearchService::class);
    $reflection = new ReflectionClass($service);
    $confidenceForScore = $reflection->getMethod('confidenceForScore');
    $confidenceForScore->setAccessible(true);

    expect($confidenceForScore->invoke($service, 0.71))->toBe('approximate_match')
        ->and($confidenceForScore->invoke($service, 0.61))->toBe('no_match');
});

test('visual search returns a safe message when the index is missing', function () {
    drawShoeFixture('missing-index-shoe.png', '#1f1f1f');
    makeStorefrontProduct([
        'primary_image_url' => visualSearchFixtureUrl('missing-index-shoe.png'),
        'image_alt' => 'Missing index shoe',
    ]);

    assistantPost($this, route('storefront.assistant.visual-search'), [
        'image' => uploadFromFixture(visualSearchFixturePath('missing-index-shoe.png'), 'missing-index-shoe.png'),
    ], [
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJsonPath('match.confidence', 'no_match')
        ->assertJsonPath('match.reason', 'index_unavailable')
        ->assertJsonPath('match.engine', 'catalog_guided')
        ->assertJsonPath('answer', 'I could not compare the photo directly right now, but I picked active catalog options that still fit the style cues and filters you shared.')
        ->assertJsonCount(1, 'products');
});

test('visual search no-index fallback excludes inactive products from recommendations', function () {
    drawShoeFixture('inactive-fallback-query.png', '#1f1f1f');

    makeStorefrontProduct([
        'name' => 'Active Night Runner',
        'slug' => 'active-night-runner',
        'primary_image_url' => visualSearchFixtureUrl('inactive-fallback-query.png'),
        'image_alt' => 'Active Night Runner product image',
    ]);

    makeStorefrontProduct([
        'name' => 'Inactive Night Runner',
        'slug' => 'inactive-night-runner',
        'primary_image_url' => visualSearchFixtureUrl('inactive-fallback-query.png'),
        'image_alt' => 'Inactive Night Runner product image',
        'status' => 'inactive',
    ], [
        'sku' => 'YS-INA-6200-9',
    ]);

    assistantPost($this, route('storefront.assistant.visual-search'), [
        'image' => uploadFromFixture(visualSearchFixturePath('inactive-fallback-query.png'), 'inactive-fallback-query.png'),
        'category' => 'running',
        'color' => 'black',
    ], [
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJsonPath('match.reason', 'index_unavailable')
        ->assertJsonPath('products.0.slug', 'active-night-runner')
        ->assertJsonMissing([
            'slug' => 'inactive-night-runner',
        ]);
});

test('visual search falls back safely when the embedding service is unavailable', function () {
    drawShoeFixture('fallback-source-shoe.png', '#355fc7');
    $product = makeStorefrontProduct([
        'name' => 'Fallback Runner',
        'slug' => 'fallback-runner',
        'primary_image_url' => visualSearchFixtureUrl('fallback-source-shoe.png'),
        'image_alt' => 'Fallback Runner product image',
    ]);

    config()->set('storefront.assistant.visual_search.embedding.python_binary', 'python-does-not-exist');

    $this->artisan('visual-search:index', ['--fresh' => true])->assertExitCode(0);

    assistantPost($this, route('storefront.assistant.visual-search'), [
        'image' => uploadFromFixture(visualSearchFixturePath('fallback-source-shoe.png'), 'fallback-source-shoe.png'),
    ], [
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJsonPath('products.0.slug', $product->slug)
        ->assertJsonPath('match.engine', 'fallback');
});

test('visual search index command builds entries for catalog images', function () {
    drawShoeFixture('index-command-shoe.png', '#355fc7');
    makeStorefrontProduct([
        'primary_image_url' => visualSearchFixtureUrl('index-command-shoe.png'),
        'image_alt' => 'Indexed product image',
    ]);

    $this->artisan('visual-search:index', ['--fresh' => true])
        ->assertExitCode(0);

    expect(VisualSearchIndexEntry::query()->count())->toBeGreaterThan(0);
});

test('visual search index stores distinct image URLs with embeddings for product images', function () {
    drawShoeFixture('index-unique-one.png', '#355fc7');
    drawShoeFixture('index-unique-two.png', '#1f1f1f');
    drawShoeFixture('index-unique-three.png', '#b68f2a');

    makeStorefrontProduct([
        'name' => 'Index One',
        'slug' => 'index-one',
        'primary_image_url' => visualSearchFixtureUrl('index-unique-one.png'),
        'image_alt' => 'Index One product image',
    ], ['sku' => 'YS-IN1-6200-9']);

    makeStorefrontProduct([
        'name' => 'Index Two',
        'slug' => 'index-two',
        'primary_image_url' => visualSearchFixtureUrl('index-unique-two.png'),
        'image_alt' => 'Index Two product image',
    ], ['sku' => 'YS-IN2-6200-9']);

    makeStorefrontProduct([
        'name' => 'Index Three',
        'slug' => 'index-three',
        'primary_image_url' => visualSearchFixtureUrl('index-unique-three.png'),
        'image_alt' => 'Index Three product image',
    ], ['sku' => 'YS-IN3-6200-9']);

    $this->artisan('visual-search:index', ['--fresh' => true])
        ->assertExitCode(0);

    expect(VisualSearchIndexEntry::query()->count())->toBe(3)
        ->and(VisualSearchIndexEntry::query()->distinct('image_url')->count('image_url'))->toBe(3)
        ->and(VisualSearchIndexEntry::query()->whereNotNull('embedding_vector')->count())->toBe(3);
});

test('visual search clear command removes indexed entries', function () {
    drawShoeFixture('clear-command-shoe.png', '#3d54c4');
    makeStorefrontProduct([
        'primary_image_url' => visualSearchFixtureUrl('clear-command-shoe.png'),
        'image_alt' => 'Clearable product image',
    ]);

    $this->artisan('visual-search:index', ['--fresh' => true])
        ->assertExitCode(0);

    expect(VisualSearchIndexEntry::query()->count())->toBeGreaterThan(0);

    $this->artisan('visual-search:clear')
        ->assertExitCode(0);

    expect(VisualSearchIndexEntry::query()->count())->toBe(0);
});

test('visual search health command reports embedding and index status', function () {
    drawShoeFixture('health-command-shoe.png', '#3d54c4');
    makeStorefrontProduct([
        'primary_image_url' => visualSearchFixtureUrl('health-command-shoe.png'),
        'image_alt' => 'Health product image',
    ]);

    $this->artisan('visual-search:index', ['--fresh' => true])
        ->assertExitCode(0);

    $this->artisan('visual-search:health')
        ->assertExitCode(0);
});

test('catalog search supports color keywords, price filters, and the cart label', function () {
    $matchingProduct = makeStorefrontProduct([
        'name' => 'Night Runner',
        'slug' => 'night-runner',
        'base_price' => 5990,
    ]);

    makeStorefrontProduct([
        'name' => 'Ivory Prestige',
        'slug' => 'ivory-prestige',
        'base_price' => 6890,
        'category_name' => 'Sneakers',
        'category_slug' => 'sneakers',
    ], [
        'sku' => 'YS-IVR-6890-8',
        'option_values' => [
            'size' => '8',
            'color' => 'Ivory',
        ],
    ]);

    $this->get(route('storefront.shop', [
        'search' => 'black shoes',
        'max_price' => 6000,
    ]))
        ->assertOk()
        ->assertSeeText($matchingProduct->name)
        ->assertDontSeeText('Ivory Prestige')
        ->assertSee('aria-label="Cart"', escape: false)
        ->assertSee('title="Cart"', escape: false)
        ->assertSeeText('Find similar by image')
        ->assertSeeText('Drag & drop a shoe photo here')
        ->assertSee('data-inline-visual-search-trigger', escape: false)
        ->assertSee('data-inline-visual-search-clear', escape: false)
        ->assertSee('data-storefront-product-grid', escape: false)
        ->assertDontSee('data-chat-open-visual', escape: false);
});
