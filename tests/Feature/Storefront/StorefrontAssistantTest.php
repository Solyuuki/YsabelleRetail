<?php

use App\Models\Cart\Cart;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Models\Storefront\VisualSearchIndexEntry;
use App\Support\Storefront\ColorFamilyNormalizer;
use App\Models\User;
use App\Services\Storefront\VisualProductSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('storefront.assistant.ai.enabled', false);
});

function ensureVisualSearchGdAvailable(): void
{
    foreach ([
        'imagecreatetruecolor',
        'imagecreatefrompng',
        'imagecreatefromstring',
        'imagepng',
        'imagejpeg',
        'imagedestroy',
    ] as $function) {
        if (! function_exists($function)) {
            \PHPUnit\Framework\Assert::markTestSkipped('GD extension support is not available in this environment.');
        }
    }
}

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
    ensureVisualSearchGdAvailable();

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
    ensureVisualSearchGdAvailable();

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
    ensureVisualSearchGdAvailable();

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
    ensureVisualSearchGdAvailable();

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
    ensureVisualSearchGdAvailable();

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

function assistantContextFor($test, string $message): array
{
    return assistantPostJson($test, route('storefront.assistant.message'), [
        'message' => $message,
    ])->json('assistant_context') ?? [];
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

test('assistant prioritizes an exact active product name over broader similar matches', function () {
    $exact = makeStorefrontProduct([
        'name' => 'Atlas Highstreet',
        'slug' => 'atlas-highstreet',
        'short_description' => 'Atlas Highstreet exact product.',
    ], [
        'sku' => 'YS-ATL-6200-9',
    ]);

    makeStorefrontProduct([
        'name' => 'Atlas Highstreet Runner',
        'slug' => 'atlas-highstreet-runner',
        'short_description' => 'Nearby Atlas-named running option.',
    ], [
        'sku' => 'YS-ATR-6200-9',
    ]);

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'Can you find Atlas Highstreet',
    ])
        ->assertOk()
        ->assertJsonPath('products.0.slug', $exact->slug)
        ->assertJsonPath('answer', 'Yes — I found Atlas Highstreet.');
});

test('assistant returns Atlas Highstreet first for find me phrasing', function () {
    $exact = makeStorefrontProduct([
        'name' => 'Atlas Highstreet',
        'slug' => 'atlas-highstreet',
    ], [
        'sku' => 'YS-ATL-6400-9',
    ]);

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'find me Atlas Highstreet',
    ])
        ->assertOk()
        ->assertJsonPath('products.0.slug', $exact->slug)
        ->assertJsonPath('answer', 'Yes — I found Atlas Highstreet.');
});

test('assistant returns exact product matches for direct availability and show-me requests', function () {
    $product = makeStorefrontProduct([
        'name' => 'Carbon Trace',
        'slug' => 'carbon-trace',
    ], [
        'sku' => 'YS-CBT-6200-9',
    ]);

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'do you have Carbon Trace',
    ])
        ->assertOk()
        ->assertJsonPath('products.0.slug', $product->slug)
        ->assertJsonPath('answer', 'Yes — I found Carbon Trace.');

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'Show me Carbon Trace',
    ])
        ->assertOk()
        ->assertJsonPath('products.0.slug', $product->slug)
        ->assertJsonPath('answer', 'Yes — I found Carbon Trace.');
});

test('assistant uses current product page context for this-product requests', function () {
    $product = makeStorefrontProduct([
        'name' => 'Atlas Highstreet',
        'slug' => 'atlas-highstreet',
    ], [
        'sku' => 'YS-ATL-6210-9',
    ]);

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'Show me this product',
        'page_context' => [
            'current_product' => [
                'slug' => $product->slug,
                'name' => $product->name,
                'style_code' => $product->style_code,
            ],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('products.0.slug', $product->slug)
        ->assertJsonPath('answer', 'You are currently viewing Atlas Highstreet.');
});

test('assistant does not hallucinate an exact product when the requested name is absent', function () {
    makeStorefrontProduct([
        'name' => 'Night Runner',
        'slug' => 'night-runner',
    ], [
        'sku' => 'YS-NGT-6100-9',
    ]);

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'Do you have Moonlight Parade?',
    ])
        ->assertOk()
        ->assertJsonMissing([
            'slug' => 'moonlight-parade',
        ])
        ->assertJsonPath(
            'answer',
            'I did not find an exact match, but these are the closest real products I found.'
        );
});

test('assistant handles taglish black shoe and running shoe buyer queries', function () {
    $blackRunner = makeStorefrontProduct([
        'name' => 'Shadow Stride',
        'slug' => 'shadow-stride',
        'description' => 'Black running shoes for daily training.',
        'short_description' => 'Black running shoe.',
    ], [
        'sku' => 'YS-SHS-6200-9',
        'option_values' => [
            'size' => '9',
            'color' => 'Black',
        ],
    ]);

    makeStorefrontProduct([
        'name' => 'Ivory Street',
        'slug' => 'ivory-street',
        'category_name' => 'Sneakers',
        'category_slug' => 'sneakers',
        'description' => 'White sneaker for city wear.',
    ], [
        'sku' => 'YS-IVS-6200-8',
        'option_values' => [
            'size' => '8',
            'color' => 'White',
        ],
    ]);

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'meron ba kayo black shoes',
    ])
        ->assertOk()
        ->assertJsonPath('products.0.slug', $blackRunner->slug);

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'hanap moko running shoes',
    ])
        ->assertOk()
        ->assertJsonPath('products.0.slug', $blackRunner->slug);
});

test('ProductDiscovery parses budget color and category from under 5k na white sneakers', function () {
    $service = app(\App\Services\Storefront\ProductDiscoveryService::class);
    $criteria = $service->buildCriteriaFromText('under 5k na white sneakers');

    expect($criteria['max_price'])->toBe(5000.0)
        ->and($criteria['color'])->toBe('white')
        ->and($criteria['category'])->toBe('sneakers');
});

test('assistant returns affordable products for mura buyer phrasing', function () {
    $cheap = makeStorefrontProduct([
        'name' => 'Budget Walker',
        'slug' => 'budget-walker',
        'base_price' => 2490,
    ], [
        'sku' => 'YS-BDW-2490-8',
    ]);

    makeStorefrontProduct([
        'name' => 'Premium Haze',
        'slug' => 'premium-haze',
        'base_price' => 6990,
    ], [
        'sku' => 'YS-PRH-6990-9',
    ]);

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'pre may mura ba kayo',
    ])
        ->assertOk()
        ->assertJsonPath('products.0.slug', $cheap->slug)
        ->assertJsonPath('answer', 'These are the most affordable active pairs I found right now.');
});

test('assistant uses current product context for size questions instead of the size guide', function () {
    $product = makeStorefrontProduct([
        'name' => 'Atlas Highstreet',
        'slug' => 'atlas-highstreet',
    ], [
        'sku' => 'YS-ATL-6500-9',
        'option_values' => [
            'size' => '9',
            'color' => 'Black',
        ],
    ]);

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'may size 9 ba nito',
        'page_context' => [
            'current_product' => [
                'slug' => $product->slug,
                'name' => $product->name,
                'style_code' => $product->style_code,
            ],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('products.0.slug', $product->slug)
        ->assertJsonPath('answer', 'Yes, Atlas Highstreet is available in size 9 right now.')
        ->assertJsonPath('assistant_context.last_intent', 'ecommerce_product_search');
});

test('assistant checks named product sizes truthfully for taglish availability questions', function () {
    $product = makeStorefrontProduct([
        'name' => 'Atlas Highstreet',
        'slug' => 'atlas-highstreet',
    ], [
        'sku' => 'YS-ATL-6510-9',
        'option_values' => [
            'size' => '9',
            'color' => 'Black',
        ],
    ]);

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'meron bang size 10 yung Atlas Highstreet',
    ])
        ->assertOk()
        ->assertJsonPath('products.0.slug', $product->slug)
        ->assertJsonPath('answer', 'I could not find size 10 for Atlas Highstreet. Available sizes right now: 9.');
});

test('assistant uses current product context for find this shoe requests', function () {
    $product = makeStorefrontProduct([
        'name' => 'Dune Ascent',
        'slug' => 'dune-ascent',
    ], [
        'sku' => 'YS-DNA-6200-9',
    ]);

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'find this shoe',
        'page_context' => [
            'current_product' => [
                'slug' => $product->slug,
                'name' => $product->name,
                'style_code' => $product->style_code,
            ],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('products.0.slug', $product->slug)
        ->assertJsonPath('answer', 'You are currently viewing Dune Ascent.');
});

test('assistant resolves typo and partial product-name queries when the match is unambiguous', function () {
    $product = makeStorefrontProduct([
        'name' => 'Atlas Highstreet',
        'slug' => 'atlas-highstreet',
    ], [
        'sku' => 'YS-ATL-6520-9',
    ]);

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'find Atls Highstreet',
    ])
        ->assertOk()
        ->assertJsonPath('products.0.slug', $product->slug)
        ->assertJsonPath('answer', 'I did not find an exact match, but Atlas Highstreet is the closest real product name match I found.');

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'find Highstreet',
    ])
        ->assertOk()
        ->assertJsonPath('products.0.slug', $product->slug);
});

test('assistant answers hiking use-case searches with hiking-oriented results', function () {
    $boot = makeStorefrontProduct([
        'name' => 'Dune Ascent',
        'slug' => 'dune-ascent',
        'category_name' => 'Boots',
        'category_slug' => 'boots-high-cut',
        'description' => 'Trail and hiking boot for rough ground.',
    ], [
        'sku' => 'YS-DNA-6600-9',
        'option_values' => [
            'size' => '9',
            'color' => 'Graphite',
        ],
    ]);

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'best shoes for hiking',
    ])
        ->assertOk()
        ->assertJsonPath('products.0.slug', $boot->slug)
        ->assertJsonPath('answer', 'These are the strongest hiking-oriented options I found in the active catalog.');
});

test('assistant is truthful about inactive exact products', function () {
    makeStorefrontProduct([
        'name' => 'Carbon Trace',
        'slug' => 'carbon-trace',
        'status' => 'inactive',
    ], [
        'sku' => 'YS-CBT-6700-9',
    ]);

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'do you have Carbon Trace',
    ])
        ->assertOk()
        ->assertJsonPath('answer', 'Carbon Trace exists in the catalog, but it is not currently available in the active storefront.');
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

test('assistant returns storefront location help instead of treating it as out of scope', function () {
    makeStorefrontProduct();

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'Where is this located?',
    ])
        ->assertOk()
        ->assertJsonPath('actions.0.label', 'Contact Support')
        ->assertJsonCount(0, 'products');

    expect(assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'Where is this located?',
    ])->json('answer'))
        ->toContain('Bonifacio Global City')
        ->toContain('Separate branch listings are not available');
});

test('assistant returns login guidance for storefront support questions', function () {
    makeStorefrontProduct();

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'How to login?',
    ])
        ->assertOk()
        ->assertJsonPath('answer', 'To log in, open Sign in, enter your email and password, then submit the form. If you do not have an account yet, use Create Account first.')
        ->assertJsonPath('actions.0.label', 'Login')
        ->assertJsonPath('actions.1.label', 'Create Account')
        ->assertJsonPath('assistant_context.last_intent', 'support.login')
        ->assertJsonPath('assistant_context.last_topic', 'login')
        ->assertJsonPath('assistant_context.last_domain', 'support')
        ->assertJsonCount(0, 'products');
});

test('assistant recovers quick auth options from previous login context', function () {
    makeStorefrontProduct();

    $assistantContext = assistantContextFor($this, 'How to login?');

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => "there's a quick sign also?",
        'assistant_context' => $assistantContext,
    ])
        ->assertOk()
        ->assertJsonPath('actions.0.label', 'Login')
        ->assertJsonPath('actions.1.label', 'Create Account')
        ->assertJsonPath('assistant_context.last_intent', 'support.auth_quick_options');

    expect(assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => "there's a quick sign also?",
        'assistant_context' => $assistantContext,
    ])->json('answer'))
        ->toContain('quick sign-in buttons')
        ->toContain('Google')
        ->toContain('Microsoft')
        ->toContain('GitHub')
        ->toContain('Phone OTP and Email Magic Link are not enabled yet');
});

test('assistant recovers quick auth options from vague taglish follow up', function () {
    makeStorefrontProduct();

    $assistantContext = assistantContextFor($this, 'How to login?');

    expect(assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'may mabilis?',
        'assistant_context' => $assistantContext,
    ])->json('answer'))
        ->toContain('quick sign-in buttons')
        ->toContain('Phone OTP and Email Magic Link are not enabled yet');
});

test('assistant reports phone otp as disabled from login context follow ups', function () {
    makeStorefrontProduct();

    $assistantContext = assistantContextFor($this, 'How to login?');

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'phone pwede?',
        'assistant_context' => $assistantContext,
    ])
        ->assertOk()
        ->assertJsonPath('assistant_context.last_intent', 'support.auth_phone_option_status');

    expect(assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'phone pwede?',
        'assistant_context' => $assistantContext,
    ])->json('answer'))
        ->toContain('Phone OTP is not enabled yet');
});

test('assistant reports otp as disabled from login context follow ups', function () {
    makeStorefrontProduct();

    $assistantContext = assistantContextFor($this, 'How to login?');

    expect(assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'OTP?',
        'assistant_context' => $assistantContext,
    ])->json('answer'))
        ->toContain('Phone OTP is not enabled yet');
});

test('assistant reports email magic link as disabled from login context follow ups', function () {
    makeStorefrontProduct();

    $assistantContext = assistantContextFor($this, 'How to login?');

    expect(assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'email link?',
        'assistant_context' => $assistantContext,
    ])->json('answer'))
        ->toContain('Email Magic Link is not enabled yet');
});

test('assistant recognizes quick sign in questions without prior context', function () {
    makeStorefrontProduct();

    expect(assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'quick sign in?',
    ])->json('answer'))
        ->toContain('quick sign-in buttons');
});

test('assistant recognizes quick login phrasing in taglish without prior context', function () {
    makeStorefrontProduct();

    expect(assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'may quick login?',
    ])->json('answer'))
        ->toContain('quick sign-in buttons');
});

test('assistant recovers location help from bounded location context', function () {
    makeStorefrontProduct();

    $assistantContext = assistantContextFor($this, 'Where is this located?');

    expect(assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'san yan?',
        'assistant_context' => $assistantContext,
    ])->json('answer'))
        ->toContain('Bonifacio Global City')
        ->toContain('Separate branch listings are not available');
});

test('assistant returns bounded topic options from prior support guidance context', function () {
    makeStorefrontProduct();

    $assistantContext = assistantContextFor($this, 'How to use image search?');

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'ano pa options?',
        'assistant_context' => $assistantContext,
    ])
        ->assertOk()
        ->assertJsonPath('actions.0.label', 'Start Image Search')
        ->assertJsonPath('actions.1.label', 'Browse Products');

    expect(assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'ano pa options?',
        'assistant_context' => $assistantContext,
    ])->json('answer'))
        ->toContain('image search help')
        ->toContain('Start Image Search')
        ->toContain('Browse Products');
});

test('assistant returns sign up guidance for storefront support questions', function () {
    makeStorefrontProduct();

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'How do I sign up?',
    ])
        ->assertOk()
        ->assertJsonPath('answer', 'To create an account, open Create Account, enter your email, display name, password, and password confirmation, then submit the form. If you already have an account, use Sign in instead.')
        ->assertJsonPath('actions.0.label', 'Create Account')
        ->assertJsonPath('actions.1.label', 'Login')
        ->assertJsonCount(0, 'products');
});

test('assistant returns ordering guidance for how to order questions', function () {
    makeStorefrontProduct();

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'How to order?',
    ])
        ->assertOk()
        ->assertJsonPath('answer', 'To order, browse or search the catalog, open a product, choose a size if needed, and add it to your cart. Then open the cart, continue to checkout, sign in if required, and complete the shipping and payment details shown there.')
        ->assertJsonPath('actions.0.label', 'Browse Products')
        ->assertJsonPath('actions.1.label', 'Open Cart')
        ->assertJsonCount(0, 'products');
});

test('assistant returns checkout guidance for storefront support questions', function () {
    makeStorefrontProduct();

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'How to checkout?',
    ])
        ->assertOk()
        ->assertJsonPath('actions.0.label', 'Open Cart')
        ->assertJsonPath('actions.1.label', 'Login')
        ->assertJsonCount(0, 'products');

    expect(assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'How to checkout?',
    ])->json('answer'))
        ->toContain('To check out, review your cart')
        ->toContain('Cash on Delivery')
        ->toContain('Card (simulated)');
});

test('assistant returns shipping support guidance', function () {
    makeStorefrontProduct();

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'Shipping?',
    ])
        ->assertOk()
        ->assertJsonPath('actions.0.label', 'Shipping Info')
        ->assertJsonPath('actions.1.label', 'Contact Support')
        ->assertJsonCount(0, 'products');

    expect(assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'Shipping?',
    ])->json('answer'))
        ->toContain('Shipping is free for orders above PHP 5,000')
        ->toContain('source of truth for shipping charges');
});

test('assistant returns returns support guidance', function () {
    makeStorefrontProduct();

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'Returns?',
    ])
        ->assertOk()
        ->assertJsonPath('actions.0.label', 'Returns Info')
        ->assertJsonPath('actions.1.label', 'Contact Support')
        ->assertJsonCount(0, 'products');

    expect(assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'Returns?',
    ])->json('answer'))
        ->toContain('14-day return window');
});

test('assistant returns size guide support guidance', function () {
    makeStorefrontProduct();

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'Size guide?',
    ])
        ->assertOk()
        ->assertJsonPath('actions.0.label', 'Open Size Guide')
        ->assertJsonPath('actions.1.label', 'Contact Support')
        ->assertJsonCount(0, 'products');

    expect(assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'Size guide?',
    ])->json('answer'))
        ->toContain('The Size Guide helps you compare');
});

test('assistant returns contact support guidance', function () {
    makeStorefrontProduct();

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'Contact?',
    ])
        ->assertOk()
        ->assertJsonPath('actions.0.label', 'Contact Support')
        ->assertJsonCount(0, 'products');

    expect(assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'Contact?',
    ])->json('answer'))
        ->toContain('ysabelleretail@gmail.com')
        ->toContain('0976 650 0867');
});

test('assistant returns image search help for manual questions', function () {
    makeStorefrontProduct();

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'How to use image search?',
    ])
        ->assertOk()
        ->assertJsonPath('answer', 'To use image search, open Visual Search, upload a clear shoe photo or screenshot, add optional refinements like color or category, then submit. Review the suggested matches and refine again if you need a closer result.')
        ->assertJsonPath('actions.0.label', 'Start Image Search')
        ->assertJsonPath('actions.0.target', 'visual-search')
        ->assertJsonCount(0, 'products');
});

test('assistant returns bounded site guidance for user manual questions', function () {
    makeStorefrontProduct();

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'How do I use this website?',
    ])
        ->assertOk()
        ->assertJsonPath('actions.0.label', 'Browse Products')
        ->assertJsonPath('actions.1.label', 'Start Image Search')
        ->assertJsonCount(0, 'products');

    expect(assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'How do I use this website?',
    ])->json('answer'))
        ->toContain('browse the shop')
        ->toContain('Visual Search');
});

test('assistant keeps the existing visual search trigger available from chat', function () {
    makeStorefrontProduct();

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'Find similar by image',
    ])
        ->assertOk()
        ->assertJsonPath('actions.0.label', 'Open Visual Search')
        ->assertJsonPath('actions.0.target', 'visual-search')
        ->assertJsonCount(0, 'products');
});

test('assistant redirects out of scope questions back to storefront help', function () {
    makeStorefrontProduct();

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'What is the capital of France?',
    ])
        ->assertOk()
        ->assertJsonPath('answer', 'I can only help with Ysabelle Retail Shop support, products, cart, checkout, and catalog image search.')
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

test('assistant keeps nonsense low-signal inputs in clarification mode with no products', function () {
    makeStorefrontProduct();

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'bolbol',
    ])
        ->assertOk()
        ->assertJsonPath('answer', 'I can help with shoe recommendations, stock, sizing, cart, checkout, or image search. Tell me your preferred color, budget, size, or use case and I will guide you from there.')
        ->assertJsonCount(0, 'products');

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'asdasdasd',
    ])
        ->assertOk()
        ->assertJsonPath('answer', 'I can help with shoe recommendations, stock, sizing, cart, checkout, or image search. Tell me your preferred color, budget, size, or use case and I will guide you from there.')
        ->assertJsonCount(0, 'products');
});

test('assistant uses a bounded clarifier for vague store-system phrasing without trusted context', function () {
    makeStorefrontProduct();

    assistantPostJson($this, route('storefront.assistant.message'), [
        'message' => 'phone pwede?',
        'assistant_context' => 'not-trusted',
    ])
        ->assertOk()
        ->assertJsonPath('answer', 'Are you asking about login options, checkout options, or contact/location details?')
        ->assertJsonPath('assistant_context.last_intent', 'fallback');
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
        ->assertJsonPath('search_confidence', 'high_confidence')
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

test('visual search accepts png screenshot uploads without failing validation', function () {
    assistantPost($this, route('storefront.assistant.visual-search'), [
        'image' => uploadFromFixture(public_path('apple-touch-icon.png'), 'Screenshot (711).png'),
    ], [
        'Accept' => 'application/json',
    ])
        ->assertOk();
});

test('visual search returns a safe index-unavailable failure when no engine can run', function () {
    config()->set('storefront.assistant.visual_search.embedding.enabled', false);

    assistantPost($this, route('storefront.assistant.visual-search'), [
        'image' => uploadFromFixture(public_path('images/products/running/aurum-runner.jpg'), 'aurum-runner.jpg'),
    ], [
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('match.reason', 'index_unavailable')
        ->assertJsonPath('products', [])
        ->assertJsonPath('answer', 'Visual search is unavailable because the current catalog index is empty.');
});

test('visual search gives screenshot crop guidance for screenshot-like uploads with weak shoe signal', function () {
    makeStorefrontProduct([
        'name' => 'Guide Runner',
        'slug' => 'guide-runner',
        'primary_image_url' => url('images/products/running/aurum-runner.jpg'),
        'image_alt' => 'Guide Runner product image',
    ]);

    $this->artisan('visual-search:index', ['--fresh' => true])->assertExitCode(0);
    config()->set('storefront.assistant.visual_search.embedding.enabled', false);

    assistantPost($this, route('storefront.assistant.visual-search'), [
        'image' => uploadFromFixture(public_path('apple-touch-icon.png'), 'Screenshot (711).png'),
    ], [
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('match.reason', 'screenshot_needs_crop')
        ->assertJsonPath('answer', 'I can read the screenshot, but the shoe is too small/noisy. Try cropping closer.');
});

test('visual search surfaces closest matches for screenshot-like candidate band scores', function () {
    $service = app(VisualProductSearchService::class);
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('shouldSurfaceClosestMatches');
    $method->setAccessible(true);

    expect($method->invoke($service, 'screenshot_needs_crop', ['confidence_score' => 0.7]))->toBeTrue()
        ->and($method->invoke($service, 'low_similarity', ['visual_score' => 0.65]))->toBeTrue()
        ->and($method->invoke($service, 'non_shoe', ['confidence_score' => 0.7]))->toBeFalse()
        ->and($method->invoke($service, 'screenshot_needs_crop', ['confidence_score' => 0.4]))->toBeFalse();
});

test('visual search fails safely for unrelated non-shoe images', function () {
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
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('match.reason', 'non_shoe')
        ->assertJsonPath('answer', 'I couldn\'t scan that image. Try another shoe photo.')
        ->assertJsonCount(0, 'products');
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
        'category_name' => 'Sneakers',
        'category_slug' => 'sneakers',
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

test('visual search keeps non-shoe uploads from turning into fake hinted matches', function () {
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
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('match.reason', 'non_shoe')
        ->assertJsonCount(0, 'products');
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
    ensureVisualSearchGdAvailable();

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

    expect($confidenceForScore->invoke($service, 0.74))->toBe('approximate_match')
        ->and($confidenceForScore->invoke($service, 0.71))->toBe('no_match')
        ->and($confidenceForScore->invoke($service, 0.61))->toBe('no_match');
});

test('visual search maps embedding and fallback candidates to confidence-aware result bands', function () {
    $service = app(VisualProductSearchService::class);
    $reflection = new ReflectionClass($service);
    $searchConfidenceForCandidate = $reflection->getMethod('searchConfidenceForCandidate');
    $searchConfidenceForCandidate->setAccessible(true);

    expect($searchConfidenceForCandidate->invoke($service, ['confidence' => 'strong_match'], 'embedding'))->toBe('high_confidence')
        ->and($searchConfidenceForCandidate->invoke($service, ['confidence' => 'likely_match'], 'embedding'))->toBe('medium_confidence')
        ->and($searchConfidenceForCandidate->invoke($service, ['confidence' => 'approximate_match'], 'embedding'))->toBe('low_confidence')
        ->and($searchConfidenceForCandidate->invoke($service, ['confidence' => 'strong_match'], 'fallback'))->toBe('low_confidence');
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
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('search_confidence', 'failed')
        ->assertJsonPath('match.confidence', 'no_match')
        ->assertJsonPath('match.reason', 'index_unavailable')
        ->assertJsonPath('match.engine', 'catalog_unavailable')
        ->assertJsonPath('answer', 'Visual search is unavailable because the current catalog index is empty.')
        ->assertJsonCount(0, 'products');
});

test('visual search returns index stale when embeddings do not match the active version', function () {
    drawShoeFixture('stale-index-source.png', '#1f1f1f');
    makeStorefrontProduct([
        'primary_image_url' => visualSearchFixtureUrl('stale-index-source.png'),
        'image_alt' => 'Stale index source image',
    ]);

    $this->artisan('visual-search:index', ['--fresh' => true])
        ->assertExitCode(0);

    config()->set('storefront.assistant.visual_search.embedding.version', 'clip-b32-test-stale');

    assistantPost($this, route('storefront.assistant.visual-search'), [
        'image' => uploadFromFixture(visualSearchFixturePath('stale-index-source.png'), 'stale-index-source.png'),
    ], [
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('match.reason', 'index_stale')
        ->assertJsonPath('match.engine', 'catalog_unavailable')
        ->assertJsonPath('answer', 'I couldn\'t scan that image right now because visual search is refreshing. Please try again shortly.')
        ->assertJsonCount(0, 'products');

    expect(app(\App\Services\Storefront\VisualSearchIndexService::class)->health())
        ->toMatchArray([
            'status' => 'index_stale',
            'entries' => 1,
            'embedded_entries' => 1,
            'entries_matching_current_version' => 0,
            'outdated_embedded_entries' => 1,
        ]);
});

test('visual search no-index failures do not leak catalog recommendations', function () {
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
        ->assertJsonCount(0, 'products')
        ->assertJsonMissing([
            'slug' => 'inactive-night-runner',
        ]);
});

test('visual search self-heals a stale python config by retrying the env-file python binary', function () {
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
        ->assertJsonPath('search_confidence', 'high_confidence')
        ->assertJsonPath('answer', 'Found a strong match for this shoe.')
        ->assertJsonPath('products.0.slug', $product->slug)
        ->assertJsonPath('products.0.match.label', 'Strong visual match')
        ->assertJsonPath('match.engine', 'embedding');
});

test('visual search response copy maps strong close and nearby states honestly', function () {
    $service = app(VisualProductSearchService::class);
    $reflection = new ReflectionClass($service);
    $successfulMatchAnswer = $reflection->getMethod('successfulMatchAnswer');
    $successfulMatchAnswer->setAccessible(true);
    $lowConfidenceAnswer = $reflection->getMethod('lowConfidenceAnswer');
    $lowConfidenceAnswer->setAccessible(true);

    expect($successfulMatchAnswer->invoke($service, 'high_confidence', 'embedding', 'strong_match'))
        ->toBe('Found a strong match for this shoe.')
        ->and($successfulMatchAnswer->invoke($service, 'medium_confidence', 'embedding', 'likely_match'))
        ->toBe('This looks like a close match.')
        ->and($lowConfidenceAnswer->invoke($service, 'approximate_match'))
        ->toBe('This looks like a nearby match.')
        ->and($lowConfidenceAnswer->invoke($service, 'filter_fallback'))
        ->toBe('No exact match found. Showing closest alternatives.');
});

test('visual search keeps white sneaker matches ahead of orange and black mismatches when better options exist', function () {
    drawShoeFixture('exact-white-runner.png', '#f1efe8', '#d6d2c8');
    drawShoeFixture('nearby-ivory-runner.png', '#e6decd', '#d2c6b2');
    drawShoeFixture('orange-mismatch-runner.png', '#dd7e2f');
    drawShoeFixture('black-mismatch-runner.png', '#1f1f1f');

    $exact = makeStorefrontProduct([
        'name' => 'Arctic Sprint',
        'slug' => 'arctic-sprint',
        'primary_image_url' => visualSearchFixtureUrl('exact-white-runner.png'),
        'image_alt' => 'Arctic Sprint product image',
    ], [
        'sku' => 'YS-ARC-6200-9',
        'option_values' => [
            'size' => '9',
            'color' => 'White',
        ],
    ]);

    $nearby = makeStorefrontProduct([
        'name' => 'Ivory Sprint',
        'slug' => 'ivory-sprint',
        'primary_image_url' => visualSearchFixtureUrl('nearby-ivory-runner.png'),
        'image_alt' => 'Ivory Sprint product image',
    ], [
        'sku' => 'YS-IVS-6200-9',
        'option_values' => [
            'size' => '9',
            'color' => 'Ivory',
        ],
    ]);

    makeStorefrontProduct([
        'name' => 'Orange Flash',
        'slug' => 'orange-flash',
        'primary_image_url' => visualSearchFixtureUrl('orange-mismatch-runner.png'),
        'image_alt' => 'Orange Flash product image',
    ], [
        'sku' => 'YS-ORF-6200-9',
        'option_values' => [
            'size' => '9',
            'color' => 'Gold',
        ],
    ]);

    makeStorefrontProduct([
        'name' => 'Midnight Flash',
        'slug' => 'midnight-flash',
        'primary_image_url' => visualSearchFixtureUrl('black-mismatch-runner.png'),
        'image_alt' => 'Midnight Flash product image',
    ], [
        'sku' => 'YS-MDF-6200-9',
        'option_values' => [
            'size' => '9',
            'color' => 'Black',
        ],
    ]);

    $this->artisan('visual-search:index', ['--fresh' => true])->assertExitCode(0);

    $response = assistantPost($this, route('storefront.assistant.visual-search'), [
        'image' => uploadFromFixture(visualSearchFixturePath('exact-white-runner.png'), 'exact-white-runner.png'),
        'category' => 'running',
        'color' => 'white',
    ], [
        'Accept' => 'application/json',
    ])->assertOk()
        ->assertJsonPath('products.0.slug', $exact->slug)
        ->assertJsonPath('search_confidence', 'high_confidence');

    $returnedSlugs = collect($response->json('products'))->pluck('slug');

    expect($returnedSlugs->first())
        ->toBe($exact->slug)
        ->and($returnedSlugs->take(2)->all())
        ->not()->toContain('orange-flash')
        ->not()->toContain('midnight-flash');
});

test('visual search keeps black sneaker matches ahead of white-only sneakers when explicit black filters are present', function () {
    drawShoeFixture('black-cream-sneaker.png', '#1f1f1f', '#ede7d9');
    drawShoeFixture('white-only-sneaker.png', '#f2efe7', '#243260');

    $blackSneaker = makeStorefrontProduct([
        'name' => 'Shadow Court',
        'slug' => 'shadow-court',
        'category_name' => 'Sneakers',
        'category_slug' => 'sneakers',
        'primary_image_url' => visualSearchFixtureUrl('black-cream-sneaker.png'),
        'image_alt' => 'Shadow Court product image',
    ], [
        'sku' => 'YS-SHC-6200-9',
        'option_values' => [
            'size' => '9',
            'color' => 'Black/Cream',
        ],
    ]);

    $whiteSneaker = makeStorefrontProduct([
        'name' => 'Cloud Court',
        'slug' => 'cloud-court',
        'category_name' => 'Sneakers',
        'category_slug' => 'sneakers',
        'primary_image_url' => visualSearchFixtureUrl('white-only-sneaker.png'),
        'image_alt' => 'Cloud Court product image',
    ], [
        'sku' => 'YS-CLC-6200-9',
        'option_values' => [
            'size' => '9',
            'color' => 'White',
        ],
    ]);

    $this->artisan('visual-search:index', ['--fresh' => true])->assertExitCode(0);

    $response = assistantPost($this, route('storefront.assistant.visual-search'), [
        'image' => uploadFromFixture(visualSearchFixturePath('black-cream-sneaker.png'), 'black-cream-sneaker.png'),
        'category' => 'sneakers',
        'color' => 'black',
    ], [
        'Accept' => 'application/json',
    ])->assertOk()
        ->assertJsonPath('products.0.slug', $blackSneaker->slug)
        ->assertJsonPath('products.0.match.confidence', 'strong_match');

    expect(collect($response->json('products'))->pluck('slug')->first())
        ->toBe($blackSneaker->slug)
        ->not()->toBe($whiteSneaker->slug);
});

test('visual search returns an honest fallback when no exact explicit filter match exists', function () {
    drawShoeFixture('fallback-white-sneaker.png', '#f2efe7', '#243260');

    $whiteSneaker = makeStorefrontProduct([
        'name' => 'Cloud Court Fallback',
        'slug' => 'cloud-court-fallback',
        'category_name' => 'Sneakers',
        'category_slug' => 'sneakers',
        'primary_image_url' => visualSearchFixtureUrl('fallback-white-sneaker.png'),
        'image_alt' => 'Cloud Court Fallback product image',
    ], [
        'sku' => 'YS-CCF-6200-9',
        'option_values' => [
            'size' => '9',
            'color' => 'White',
        ],
    ]);

    $this->artisan('visual-search:index', ['--fresh' => true])->assertExitCode(0);

    assistantPost($this, route('storefront.assistant.visual-search'), [
        'image' => uploadFromFixture(visualSearchFixturePath('fallback-white-sneaker.png'), 'fallback-white-sneaker.png'),
        'category' => 'sneakers',
        'color' => 'black',
    ], [
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('search_confidence', 'low_confidence')
        ->assertJsonPath('match.reason', 'filter_fallback')
        ->assertJsonPath('answer', 'No exact black sneakers match found. Showing closest alternatives.')
        ->assertJsonPath('products.0.slug', $whiteSneaker->slug)
        ->assertJsonPath('products.0.match.confidence', 'approximate_match');
});

test('color family normalizer maps composite storefront colors into searchable families', function () {
    $normalizer = app(ColorFamilyNormalizer::class);

    expect($normalizer->familiesFromValue('Black/Cream'))
        ->toContain('black')
        ->toContain('ivory')
        ->and($normalizer->familiesFromValue('White/Navy'))
        ->toContain('white')
        ->toContain('blue')
        ->and($normalizer->familiesFromValue('Off White'))
        ->toContain('white')
        ->toContain('ivory')
        ->and($normalizer->familiesFromValue('Graphite'))
        ->toContain('graphite');
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

test('visual search index resolves storage asset urls without requiring a public storage symlink', function () {
    $directory = storage_path('app/public/testing/visual-search');

    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $filename = 'storage-index-shoe.jpg';
    $targetPath = $directory.DIRECTORY_SEPARATOR.$filename;
    copy(public_path('images/products/running/aurum-runner.jpg'), $targetPath);

    makeStorefrontProduct([
        'name' => 'Storage Indexed Runner',
        'slug' => 'storage-indexed-runner',
        'primary_image_url' => url('storage/testing/visual-search/'.$filename),
        'image_alt' => 'Storage indexed runner product image',
    ], [
        'sku' => 'YS-STO-6200-9',
    ]);

    $this->artisan('visual-search:index', ['--fresh' => true])
        ->assertExitCode(0);

    expect(VisualSearchIndexEntry::query()->count())->toBe(1)
        ->and(VisualSearchIndexEntry::query()->whereNotNull('embedding_vector')->count())->toBe(1);
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

test('chat widget renders stable composer constraints for visual search state', function () {
    makeStorefrontProduct();

    $this->get(route('storefront.shop'))
        ->assertOk()
        ->assertSee('ys-chat-input-bar w-full min-w-0 max-w-full overflow-hidden', escape: false)
        ->assertSee('ys-chat-composer-body min-w-0 overflow-hidden', escape: false)
        ->assertSee('ys-chat-composer-chip hidden max-w-full overflow-hidden', escape: false)
        ->assertSee('data-chat-send', escape: false)
        ->assertSee('data-chat-send-spinner', escape: false)
        ->assertSee('data-visual-chip-retry', escape: false);
});
