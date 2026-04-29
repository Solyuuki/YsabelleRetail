<?php

namespace App\Http\Resources\Catalog;

use App\Support\Storefront\ProductMediaResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $media = app(ProductMediaResolver::class);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'style_code' => $this->style_code,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'short_description' => $this->short_description,
            'description' => $this->description,
            'primary_image_url' => $media->imageUrlFor($this->resource),
            'image_alt' => $this->image_alt,
            'image_gallery' => $media->galleryFor($this->resource),
            'base_price' => $this->base_price,
            'compare_at_price' => $this->compare_at_price,
            'status' => $this->status,
            'is_featured' => $this->is_featured,
            'featured_rank' => $this->featured_rank,
            'track_inventory' => $this->track_inventory,
            'variants_count' => $this->whenCounted('variants'),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
