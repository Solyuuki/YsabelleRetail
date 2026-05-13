<?php

namespace App\Services\Admin;

use App\Models\Audit\AuditLog;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductVariant;
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

    public function restore(Product $product): Product
    {
        $product->forceFill(['status' => 'draft'])->save();

        return $product->fresh(['category', 'variants.inventoryItem']);
    }

    public function deletionAssessment(Product $product): array
    {
        $product->loadMissing(['variants.inventoryItem', 'reviews']);

        $variantIds = $product->variants->modelKeys();
        $orderItemsQuery = OrderItem::query()->where(function ($query) use ($product, $variantIds): void {
            $query->where('product_id', $product->id);

            if ($variantIds !== []) {
                $query->orWhereIn('product_variant_id', $variantIds);
            }
        });
        $orderItemsCount = (clone $orderItemsQuery)->count();
        $ordersCount = (clone $orderItemsQuery)
            ->whereNotNull('order_id')
            ->distinct()
            ->count('order_id');
        $reviewsCount = $product->reviews()->count();
        $stockMovementsQuery = $variantIds === []
            ? null
            : StockMovement::query()->whereIn('product_variant_id', $variantIds);
        $stockMovementsCount = $stockMovementsQuery ? (clone $stockMovementsQuery)->count() : 0;
        $stockMovementIds = $stockMovementsQuery ? (clone $stockMovementsQuery)->pluck('id')->all() : [];
        $inventoryHistory = $stockMovementsCount > 0;
        $auditDependenciesCount = $this->auditDependenciesCount($product, $variantIds, $stockMovementIds);
        $canDelete = $ordersCount === 0
            && $reviewsCount === 0
            && $stockMovementsCount === 0
            && ! $inventoryHistory
            && $auditDependenciesCount === 0;
        $recommendedAction = $product->status === 'archived' ? 'Restore' : 'Archive';

        $reasons = collect([
            $ordersCount > 0 ? "This product is linked to {$ordersCount} order(s).": null,
            $reviewsCount > 0 ? "This product has {$reviewsCount} review(s).": null,
            $stockMovementsCount > 0 ? "This product has {$stockMovementsCount} stock movement record(s).": null,
            $auditDependenciesCount > 0 ? "This product has {$auditDependenciesCount} audit-linked record(s).": null,
        ])->filter()->values()->all();

        return [
            'can_delete' => $canDelete,
            'reasons' => $reasons,
            'blocks' => [
                'orders' => $ordersCount > 0,
                'order_items' => $orderItemsCount > 0,
                'reviews' => $reviewsCount > 0,
                'stock_movements' => $stockMovementsCount > 0,
                'inventory_history' => $inventoryHistory,
                'audit_dependencies' => $auditDependenciesCount > 0,
            ],
            'counts' => [
                'orders' => $ordersCount,
                'order_items' => $orderItemsCount,
                'reviews' => $reviewsCount,
                'stock_movements' => $stockMovementsCount,
                'audit_dependencies' => $auditDependenciesCount,
            ],
            'inventory_history' => $inventoryHistory,
            'recommended_action' => $recommendedAction,
            'message' => $canDelete
                ? 'This product has no business history and can be deleted permanently.'
                : "This product has business history or audit dependencies. Permanent delete is unavailable; {$recommendedAction} it instead.",
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

    private function auditDependenciesCount(Product $product, array $variantIds, array $stockMovementIds): int
    {
        $productAuditCount = AuditLog::query()
            ->where('subject_type', $product->getMorphClass())
            ->where('subject_id', $product->id)
            ->count();
        $variantAuditCount = $variantIds === []
            ? 0
            : AuditLog::query()
                ->where('subject_type', (new ProductVariant)->getMorphClass())
                ->whereIn('subject_id', $variantIds)
                ->count();
        $stockMovementAuditCount = $stockMovementIds === []
            ? 0
            : AuditLog::query()
                ->where('subject_type', (new StockMovement)->getMorphClass())
                ->whereIn('subject_id', $stockMovementIds)
                ->count();

        return $productAuditCount + $variantAuditCount + $stockMovementAuditCount;
    }
}
