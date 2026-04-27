<?php

namespace App\Services\Inventory;

use App\Models\Catalog\ProductVariant;
use App\Models\Inventory\InventoryImportBatch;
use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\StockMovement;
use App\Models\Orders\Order;
use App\Models\User;
use App\Support\Admin\InventoryMovementType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryManager
{
    public function ensureSufficientStock(ProductVariant $variant, int $quantity): void
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be greater than zero.',
            ]);
        }

        if (! $variant->product?->track_inventory) {
            return;
        }

        $inventoryItem = $this->inventoryItemFor($variant);

        if ($inventoryItem->available_quantity < $quantity && ! $inventoryItem->allow_backorder) {
            throw ValidationException::withMessages([
                'inventory' => "Insufficient stock for {$variant->product->name} ({$variant->name}).",
            ]);
        }
    }

    public function deductForOnlineSale(
        ProductVariant $variant,
        int $quantity,
        Order $order,
        ?User $actor = null,
        array $metadata = [],
    ): StockMovement {
        return $this->applyChange(
            variant: $variant,
            quantityDelta: -1 * $quantity,
            type: InventoryMovementType::ONLINE_SALE,
            actor: $actor,
            order: $order,
            referenceNumber: $order->order_number,
            notes: 'Inventory deducted from online checkout.',
            metadata: $metadata,
        );
    }

    public function deductForWalkInSale(
        ProductVariant $variant,
        int $quantity,
        Order $order,
        ?User $actor = null,
        array $metadata = [],
    ): StockMovement {
        return $this->applyChange(
            variant: $variant,
            quantityDelta: -1 * $quantity,
            type: InventoryMovementType::WALK_IN_SALE,
            actor: $actor,
            order: $order,
            referenceNumber: $order->order_number,
            notes: 'Inventory deducted from walk-in sale.',
            metadata: $metadata,
        );
    }

    public function recordManualChange(
        ProductVariant $variant,
        int $quantity,
        string $type,
        ?User $actor = null,
        ?string $referenceNumber = null,
        ?string $notes = null,
        ?float $unitCost = null,
        ?string $supplierName = null,
        array $metadata = [],
    ): StockMovement {
        if (! in_array($type, InventoryMovementType::manualTypes(), true)) {
            throw ValidationException::withMessages([
                'type' => 'Select a valid stock movement type.',
            ]);
        }

        $delta = match ($type) {
            InventoryMovementType::STOCK_IN => abs($quantity),
            InventoryMovementType::STOCK_OUT => -1 * abs($quantity),
            InventoryMovementType::ADJUSTMENT => $quantity,
        };

        return $this->applyChange(
            variant: $variant,
            quantityDelta: $delta,
            type: $type,
            actor: $actor,
            referenceNumber: $referenceNumber,
            notes: $notes,
            unitCost: $unitCost,
            supplierName: $supplierName,
            metadata: $metadata,
        );
    }

    public function importStock(
        ProductVariant $variant,
        int $quantity,
        InventoryImportBatch $batch,
        ?User $actor = null,
        ?string $notes = null,
        ?float $unitCost = null,
        ?string $supplierName = null,
        array $metadata = [],
    ): StockMovement {
        return $this->applyChange(
            variant: $variant,
            quantityDelta: abs($quantity),
            type: InventoryMovementType::BATCH_IMPORT,
            actor: $actor,
            importBatch: $batch,
            referenceNumber: $batch->reference_number,
            notes: $notes,
            unitCost: $unitCost,
            supplierName: $supplierName,
            metadata: $metadata,
        );
    }

    public function movementSummary(): array
    {
        return StockMovement::query()
            ->selectRaw('type, SUM(quantity_delta) as total_quantity, COUNT(*) as total_records')
            ->groupBy('type')
            ->pluck('total_quantity', 'type')
            ->all();
    }

    private function applyChange(
        ProductVariant $variant,
        int $quantityDelta,
        string $type,
        ?User $actor = null,
        ?Order $order = null,
        ?InventoryImportBatch $importBatch = null,
        ?string $referenceNumber = null,
        ?string $notes = null,
        ?float $unitCost = null,
        ?string $supplierName = null,
        array $metadata = [],
    ): StockMovement {
        if ($quantityDelta === 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must not be zero.',
            ]);
        }

        return DB::transaction(function () use (
            $variant,
            $quantityDelta,
            $type,
            $actor,
            $order,
            $importBatch,
            $referenceNumber,
            $notes,
            $unitCost,
            $supplierName,
            $metadata,
        ): StockMovement {
            $inventoryItem = $this->inventoryItemFor($variant, true);

            if ($quantityDelta < 0 && ! $variant->product?->track_inventory) {
                throw ValidationException::withMessages([
                    'inventory' => 'Inventory tracking is disabled for the selected product.',
                ]);
            }

            $nextQuantity = $inventoryItem->quantity_on_hand + $quantityDelta;

            if ($nextQuantity < 0 && ! $inventoryItem->allow_backorder) {
                throw ValidationException::withMessages([
                    'inventory' => "Inventory cannot go below zero for {$variant->product->name} ({$variant->name}).",
                ]);
            }

            $inventoryItem->quantity_on_hand = $nextQuantity;
            $inventoryItem->save();

            if ($unitCost !== null) {
                $variant->forceFill(['cost_price' => $unitCost])->save();
            }

            if ($supplierName !== null && $supplierName !== '') {
                $variant->forceFill(['supplier_name' => $supplierName])->save();
            }

            return $inventoryItem->stockMovements()->create([
                'product_variant_id' => $variant->id,
                'order_id' => $order?->id,
                'import_batch_id' => $importBatch?->id,
                'actor_id' => $actor?->id,
                'type' => $type,
                'quantity_delta' => $quantityDelta,
                'reference_number' => $referenceNumber,
                'unit_cost' => $unitCost,
                'supplier_name' => $supplierName,
                'notes' => $notes,
                'metadata' => $metadata,
                'occurred_at' => now(),
            ]);
        });
    }

    private function inventoryItemFor(ProductVariant $variant, bool $lockForUpdate = false): InventoryItem
    {
        $relation = $variant->inventoryItem();

        if ($lockForUpdate) {
            $relation->lockForUpdate();
        }

        return $relation->firstOrCreate([], [
            'quantity_on_hand' => 0,
            'reserved_quantity' => 0,
            'reorder_level' => 0,
            'allow_backorder' => false,
        ]);
    }
}
