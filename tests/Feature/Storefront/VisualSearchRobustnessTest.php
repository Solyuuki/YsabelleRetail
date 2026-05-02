<?php

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('storefront.assistant.ai.enabled', false);
});

function makeVisualSearchProduct(array $overrides = [], array $variantOverrides = []): Product
{
    $category = Category::query()->firstOrCreate(
        ['slug' => $overrides['category_slug'] ?? 'running'],
        [
            'name' => $overrides['category_name'] ?? 'Running',
            'description' => fake()->sentence(),
            'is_active' => true,
            'sort_order' => 1,
        ],
    );

    $product = Product::factory()->for($category)->create(array_merge([
        'name' => 'Robust Runner',
        'slug' => 'robust-runner-'.fake()->unique()->numerify('###'),
        'style_code' => 'YS-RBT-'.fake()->unique()->numerify('####'),
        'short_description' => 'Built for robust visual search checks.',
        'description' => 'A performance shoe used for visual search verification.',
        'base_price' => 5990,
        'status' => 'active',
    ], collect($overrides)->except(['category_name', 'category_slug'])->all()));

    $variant = ProductVariant::factory()->for($product)->create(array_merge([
        'name' => 'Size 9',
        'sku' => 'YS-RBT-6000-9',
        'option_values' => [
            'size' => '9',
            'color' => 'Black',
        ],
        'price' => $product->base_price,
        'status' => 'active',
    ], $variantOverrides));

    $variant->inventoryItem()->create([
        'quantity_on_hand' => 8,
        'reserved_quantity' => 0,
        'reorder_level' => 2,
        'allow_backorder' => false,
    ]);

    return $product->fresh(['category', 'variants.inventoryItem']);
}

function robustnessFixturePath(string $filename): string
{
    $directory = public_path('testing/visual-search-robustness');

    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    return $directory.DIRECTORY_SEPARATOR.$filename;
}

function robustnessFixtureUrl(string $filename): string
{
    return url('testing/visual-search-robustness/'.$filename);
}

function allocateFixtureColor(GdImage $image, string $hex, int $alpha = 0): int
{
    $hex = ltrim($hex, '#');

    return imagecolorallocatealpha(
        $image,
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
        $alpha,
    );
}

function drawRobustShoeFixture(string $filename, string $upperHex, bool $transparent = false): string
{
    $path = robustnessFixturePath($filename);
    $image = imagecreatetruecolor(260, 160);
    imagealphablending($image, false);
    imagesavealpha($image, true);

    $background = $transparent
        ? allocateFixtureColor($image, '#ffffff', 127)
        : allocateFixtureColor($image, '#ffffff', 0);
    $upper = allocateFixtureColor($image, $upperHex, 0);
    $sole = allocateFixtureColor($image, '#202020', 0);
    $stripe = allocateFixtureColor($image, '#f6f1df', 0);

    imagefill($image, 0, 0, $background);
    imagefilledpolygon($image, [
        30, 104,
        78, 64,
        130, 58,
        180, 70,
        222, 88,
        230, 100,
        186, 106,
        144, 120,
        76, 120,
        40, 112,
    ], 10, $upper);
    imagefilledrectangle($image, 36, 114, 232, 128, $sole);
    imagefilledellipse($image, 208, 98, 40, 24, $upper);
    imagefilledpolygon($image, [
        94, 74,
        118, 68,
        154, 82,
        150, 88,
        116, 80,
        98, 84,
    ], 6, $stripe);

    imagepng($image, $path);
    imagedestroy($image);

    return $path;
}

function createWebpFixture(string $sourceFilename, string $targetFilename): string
{
    $source = imagecreatefrompng(robustnessFixturePath($sourceFilename));
    $targetPath = robustnessFixturePath($targetFilename);

    imagepalettetotruecolor($source);
    imagewebp($source, $targetPath, 82);
    imagedestroy($source);

    return $targetPath;
}

function createCompressedFixture(string $sourceFilename, string $targetFilename, int $quality = 28): string
{
    $source = imagecreatefromstring(file_get_contents(robustnessFixturePath($sourceFilename)));
    $targetPath = robustnessFixturePath($targetFilename);

    imagejpeg($source, $targetPath, $quality);
    imagedestroy($source);

    return $targetPath;
}

function uploadRobustFixture(string $path, string $name, ?string $mimeType = null): UploadedFile
{
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mime = $mimeType ?? match ($extension) {
        'jpg', 'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'heic' => 'image/heic',
        'heif' => 'image/heif',
        default => 'image/png',
    };

    return new UploadedFile($path, $name, $mime, null, true);
}

test('visual search matches transparent png uploads', function () {
    drawRobustShoeFixture('transparent-source.png', '#274fd2', transparent: true);
    $product = makeVisualSearchProduct([
        'name' => 'Transparent Runner',
        'slug' => 'transparent-runner',
        'primary_image_url' => robustnessFixtureUrl('transparent-source.png'),
        'image_alt' => 'Transparent runner product image',
    ]);

    $this->artisan('visual-search:index', ['--fresh' => true])->assertExitCode(0);

    $this->post(route('storefront.assistant.visual-search'), [
        'image' => uploadRobustFixture(robustnessFixturePath('transparent-source.png'), 'transparent-source.png'),
    ], [
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJsonPath('products.0.slug', $product->slug)
        ->assertJsonPath('match.engine', 'embedding');
});

test('visual search matches webp uploads', function () {
    if (! function_exists('imagewebp')) {
        $this->markTestSkipped('WEBP encoding is not available in this environment.');
    }

    drawRobustShoeFixture('webp-source.png', '#1f1f1f');
    createWebpFixture('webp-source.png', 'webp-query.webp');
    $product = makeVisualSearchProduct([
        'name' => 'Webp Runner',
        'slug' => 'webp-runner',
        'primary_image_url' => robustnessFixtureUrl('webp-source.png'),
        'image_alt' => 'Webp runner product image',
    ]);

    $this->artisan('visual-search:index', ['--fresh' => true])->assertExitCode(0);

    $this->post(route('storefront.assistant.visual-search'), [
        'image' => uploadRobustFixture(robustnessFixturePath('webp-query.webp'), 'webp-query.webp'),
    ], [
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJsonPath('products.0.slug', $product->slug)
        ->assertJsonPath('match.engine', 'embedding');
});

test('visual search accepts downloaded product jpg inputs', function () {
    $sourcePath = public_path('images/products/running/aurum-runner.jpg');
    $product = makeVisualSearchProduct([
        'name' => 'Downloaded Runner',
        'slug' => 'downloaded-runner',
        'primary_image_url' => url('images/products/running/aurum-runner.jpg'),
        'image_alt' => 'Downloaded runner product image',
    ]);

    $this->artisan('visual-search:index', ['--fresh' => true])->assertExitCode(0);

    $this->post(route('storefront.assistant.visual-search'), [
        'image' => uploadRobustFixture($sourcePath, 'downloaded-runner.jpg'),
    ], [
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJsonPath('products.0.slug', $product->slug)
        ->assertJsonPath('match.engine', 'embedding');
});

test('visual search keeps compressed shoe uploads searchable', function () {
    drawRobustShoeFixture('compressed-source.png', '#b68f2a');
    createCompressedFixture('compressed-source.png', 'compressed-query.jpg', 24);
    $product = makeVisualSearchProduct([
        'name' => 'Compressed Runner',
        'slug' => 'compressed-runner',
        'primary_image_url' => robustnessFixtureUrl('compressed-source.png'),
        'image_alt' => 'Compressed runner product image',
    ]);

    $this->artisan('visual-search:index', ['--fresh' => true])->assertExitCode(0);

    $this->post(route('storefront.assistant.visual-search'), [
        'image' => uploadRobustFixture(robustnessFixturePath('compressed-query.jpg'), 'compressed-query.jpg'),
    ], [
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJsonPath('products.0.slug', $product->slug);
});

test('visual search debug logs include preprocessing and similarity context', function () {
    Log::spy();
    config()->set('storefront.assistant.visual_search.debug', true);

    drawRobustShoeFixture('logged-source.png', '#2d61d2');
    makeVisualSearchProduct([
        'name' => 'Logged Runner',
        'slug' => 'logged-runner',
        'primary_image_url' => robustnessFixtureUrl('logged-source.png'),
        'image_alt' => 'Logged runner product image',
    ]);

    $this->artisan('visual-search:index', ['--fresh' => true])->assertExitCode(0);

    $this->post(route('storefront.assistant.visual-search'), [
        'image' => uploadRobustFixture(robustnessFixturePath('logged-source.png'), 'logged-source.png'),
    ], [
        'Accept' => 'application/json',
    ])->assertOk();

    Log::shouldHaveReceived('debug')
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'visual-search.match'
                && array_key_exists('upload_shoe_probability', $context)
                && array_key_exists('upload_blur_score', $context)
                && array_key_exists('preprocessing', $context)
                && ($context['similarity_reached'] ?? false) === true
                && ! empty($context['top_products']);
        })
        ->once();
});
