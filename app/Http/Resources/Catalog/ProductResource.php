<?php

namespace App\Http\Resources\Catalog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'style_code' => $this->style_code,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'short_description' => $this->short_description,
            'description' => $this->description,
            'base_price' => $this->base_price,
            'compare_at_price' => $this->compare_at_price,
            'status' => $this->status,
            'is_featured' => $this->is_featured,
            'track_inventory' => $this->track_inventory,
            'variants_count' => $this->whenCounted('variants'),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
