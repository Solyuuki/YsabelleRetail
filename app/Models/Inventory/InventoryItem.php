<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_variant_id',
        'quantity_on_hand',
        'reserved_quantity',
        'reorder_level',
        'allow_backorder',
    ];

    protected function casts(): array
    {
        return [
            'allow_backorder' => 'boolean',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Catalog\ProductVariant::class, 'product_variant_id');
    }

    public function getAvailableQuantityAttribute(): int
    {
        return max(0, $this->quantity_on_hand - $this->reserved_quantity);
    }
}
