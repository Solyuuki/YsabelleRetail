<?php

namespace App\Http\Resources\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'option_values' => $this->option_values,
            'price' => $this->price,
            'compare_at_price' => $this->compare_at_price,
            'weight_grams' => $this->weight_grams,
            'status' => $this->status,
            'inventory' => [
                'quantity_on_hand' => $this->inventoryItem?->quantity_on_hand,
                'reserved_quantity' => $this->inventoryItem?->reserved_quantity,
                'available_quantity' => $this->inventoryItem?->available_quantity,
                'allow_backorder' => $this->inventoryItem?->allow_backorder,
            ],
        ];
    }
}
