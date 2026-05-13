<?php

namespace App\Services\Admin;

use App\Models\Cart\CartItem;
use App\Models\Catalog\Product;
use App\Models\Inventory\StockMovement;
use App\Models\Orders\OrderItem;
use App\Models\Storefront\VisualSearchIndexEntry;
use App\Support\Storefront\ProductMediaPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;

class ProductLifecycleService
{
    public function __construct(
        private readonly ProductMediaPath $mediaPath,
    ) {}

    public function archive(Product $product): Product
    {
        $product->forceFill(['status' => 'archived'])->save();

        return $product->fresh(['category', 'variants.inventoryItem']);
    }

    public function deletionAssessment(Product $product): array
    {
        $product->loadMissing(['variants.inventoryItem', 'reviews']);

        $variantIds = $product->variants->modelKeys();
        $hasOrderItems = OrderItem::query()
            ->where('product_id', $product->id)
            ->when(
                $variantIds !== [],
                fn ($query) => $query->orWhereIn('product_variant_id', $variantIds),
            )
            ->exists();
        $hasReviews = $product->reviews()->exists();
        $hasStockMovements = $variantIds !== []
            && StockMovement::query()->whereIn('product_variant_id', $variantIds)->exists();
        $hasCartItems = $variantIds !== []
            && CartItem::query()->whereIn('product_variant_id', $variantIds)->exists();

        $reasons = collect([
            $hasOrderItems ? 'This product has sales history.' : null,
            $hasReviews ? 'This product has customer reviews.' : null,
            $hasStockMovements ? 'This product has inventory history.' : null,
            $hasCartItems ? 'This product is still present in an active cart.' : null,
        ])->filter()->values()->all();

        return [
            'can_delete' => $reasons === [],
            'reasons' => $reasons,
            'blocks' => [
                'order_items' => $hasOrderItems,
                'reviews' => $hasReviews,
                'stock_movements' => $hasStockMovements,
                'cart_items' => $hasCartItems,
            ],
            'message' => $reasons === []
                ? 'This product has no historical dependencies and can be deleted permanently.'
                : 'This product has sales, reviews, or inventory history. Archive it instead.',
        ];
    }

    public function delete(Product $product): void
    {
        $assessment = $this->deletionAssessment($product);

        if (! $assessment['can_delete']) {
            throw new LogicException($assessment['message']);
        }

        DB::transaction(function () use ($product): void {
            $this->deleteManagedPrimaryImage($product);

            VisualSearchIndexEntry::query()
                ->where('product_id', $product->id)
                ->delete();

            $product->delete();
        });
    }

    private function deleteManagedPrimaryImage(Product $product): void
    {
        $relativePath = $this->mediaPath->toRelativePath($product->primary_image_url);

        if (! is_string($relativePath) || ! str_starts_with($relativePath, 'storage/products/')) {
            return;
        }

        $diskPath = ltrim(substr($relativePath, strlen('storage/')), '/');

        if ($diskPath === '') {
            return;
        }

        Storage::disk('public')->delete($diskPath);
    }
}
