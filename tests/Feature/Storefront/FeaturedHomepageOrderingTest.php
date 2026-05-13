<?php

namespace Tests\Feature\Storefront;

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
use App\Services\Catalog\CatalogQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeaturedHomepageOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_featured_pieces_only_use_storefront_visible_featured_products_in_rank_then_recency_order(): void
    {
        $category = Category::factory()->create([
            'name' => 'Running Shoes',
            'slug' => 'running-shoes',
            'is_active' => true,
        ]);

        $hero = $this->createFeaturedProduct($category, [
            'name' => 'Hero Rank One',
            'slug' => 'hero-rank-one',
            'featured_rank' => 1,
            'updated_at' => now()->subDays(5),
            'created_at' => now()->subDays(5),
            'primary_image_url' => 'https://cdn.ysabelle.test/catalog/hero-rank-one.jpg',
        ]);

        $rankTwo = $this->createFeaturedProduct($category, [
            'name' => 'Rank Two',
            'slug' => 'rank-two',
            'featured_rank' => 2,
            'updated_at' => now()->subDays(4),
            'created_at' => now()->subDays(4),
            'primary_image_url' => 'https://cdn.ysabelle.test/catalog/rank-two.jpg',
        ]);

        $unrankedRecent = $this->createFeaturedProduct($category, [
            'name' => 'Unranked Recent',
            'slug' => 'unranked-recent',
            'featured_rank' => null,
            'updated_at' => now()->subMinutes(10),
            'created_at' => now()->subDays(20),
            'primary_image_url' => 'https://cdn.ysabelle.test/catalog/unranked-recent.jpg',
        ]);

        $unrankedTieNewer = $this->createFeaturedProduct($category, [
            'name' => 'Unranked Tie Newer',
            'slug' => 'unranked-tie-newer',
            'featured_rank' => null,
            'updated_at' => now()->subHours(3),
            'created_at' => now()->subDay(),
            'primary_image_url' => 'https://cdn.ysabelle.test/catalog/unranked-tie-newer.jpg',
        ]);

        $unrankedTieOlder = $this->createFeaturedProduct($category, [
            'name' => 'Unranked Tie Older',
            'slug' => 'unranked-tie-older',
            'featured_rank' => null,
            'updated_at' => $unrankedTieNewer->updated_at,
            'created_at' => now()->subDays(3),
            'primary_image_url' => 'https://cdn.ysabelle.test/catalog/unranked-tie-older.jpg',
        ]);

        $this->createFeaturedProduct($category, [
            'name' => 'Sold Out Featured',
            'slug' => 'sold-out-featured',
            'featured_rank' => 3,
            'primary_image_url' => 'https://cdn.ysabelle.test/catalog/sold-out-featured.jpg',
        ], [
            'quantity_on_hand' => 0,
            'reserved_quantity' => 0,
            'allow_backorder' => false,
        ]);

        $this->createFeaturedProduct($category, [
            'name' => 'Archived Featured',
            'slug' => 'archived-featured',
            'status' => 'archived',
            'featured_rank' => 4,
            'primary_image_url' => 'https://cdn.ysabelle.test/catalog/archived-featured.jpg',
        ]);

        $this->createProduct($category, [
            'name' => 'Non Featured Fallback',
            'slug' => 'non-featured-fallback',
            'is_featured' => false,
            'primary_image_url' => 'https://cdn.ysabelle.test/catalog/non-featured-fallback.jpg',
        ]);

        $catalogQuery = app(CatalogQueryService::class);

        $this->assertSame(
            ['Hero Rank One', 'Rank Two', 'Unranked Recent', 'Unranked Tie Newer', 'Unranked Tie Older'],
            $catalogQuery->featuredProducts(10)->pluck('name')->all(),
        );

        $this->assertSame(
            ['Rank Two', 'Unranked Recent', 'Unranked Tie Newer', 'Unranked Tie Older'],
            $catalogQuery->showcaseProducts($hero, 4)->pluck('name')->all(),
        );

        $this->get(route('storefront.home'))
            ->assertOk()
            ->assertSeeText('Rank Two')
            ->assertSeeText('Unranked Recent')
            ->assertSeeText('Unranked Tie Newer')
            ->assertSeeText('Unranked Tie Older')
            ->assertDontSeeText('Non Featured Fallback')
            ->assertDontSeeText('Sold Out Featured')
            ->assertDontSeeText('Archived Featured');
    }

    private function createFeaturedProduct(Category $category, array $attributes = [], array $inventoryAttributes = []): Product
    {
        return $this->createProduct($category, array_replace([
            'is_featured' => true,
            'status' => 'active',
            'track_inventory' => true,
        ], $attributes), $inventoryAttributes);
    }

    private function createProduct(Category $category, array $attributes = [], array $inventoryAttributes = []): Product
    {
        $product = Product::factory()
            ->for($category)
            ->create(array_replace([
                'status' => 'active',
                'is_featured' => true,
                'track_inventory' => true,
            ], $attributes));

        $variant = ProductVariant::factory()
            ->for($product)
            ->create([
                'name' => 'Default Variant',
                'sku' => strtoupper($product->slug).'-SKU',
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
