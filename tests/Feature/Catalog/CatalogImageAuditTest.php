<?php

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Services\Catalog\CatalogImageAuditService;
use Database\Seeders\Catalog\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('catalog image audit reports duplicate primary images as production blocking', function () {
    $category = Category::factory()->create([
        'name' => 'Running Shoes',
        'slug' => 'running',
        'is_active' => true,
    ]);

    Product::factory()->for($category)->create([
        'name' => 'Aurum Runner',
        'slug' => 'aurum-runner',
        'style_code' => 'YS-AUR-7490',
        'primary_image_url' => 'https://cdn.ysabelle.test/catalog/shared-runner.png',
        'image_gallery' => ['https://cdn.ysabelle.test/catalog/shared-runner-alt.png'],
        'status' => 'active',
    ]);

    Product::factory()->for($category)->create([
        'name' => 'Meridian Pace',
        'slug' => 'meridian-pace',
        'style_code' => 'YS-MRP-6890',
        'primary_image_url' => 'https://cdn.ysabelle.test/catalog/shared-runner.png',
        'image_gallery' => ['https://cdn.ysabelle.test/catalog/shared-runner-detail.png'],
        'status' => 'active',
    ]);

    $this->artisan('catalog:images:audit')
        ->expectsOutputToContain('Catalog image audit status: RED')
        ->expectsOutputToContain('Duplicate normalized primary image URLs')
        ->assertExitCode(1);
});

test('seeded catalog maintains high primary image uniqueness and gallery coverage', function () {
    $this->seed(CatalogSeeder::class);

    $audit = app(CatalogImageAuditService::class)->audit();

    expect($audit['products']['total'])->toBeGreaterThanOrEqual(100)
        ->and($audit['products']['missing_primary'])->toBe(0)
        ->and($audit['products']['with_gallery'])->toBe(0)
        ->and($audit['products']['average_gallery_images'])->toBe(0.0)
        ->and($audit['primary_images']['uniqueness_ratio'])->toBeGreaterThanOrEqual(0.95)
        ->and($audit['duplicates']['normalized_url'])->toBe([])
        ->and($audit['errors'])->toBe([]);
});
