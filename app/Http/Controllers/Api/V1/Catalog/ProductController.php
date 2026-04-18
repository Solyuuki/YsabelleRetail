<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalog\ProductIndexRequest;
use App\Http\Resources\Catalog\ProductResource;
use App\Models\Catalog\Product;
use App\Services\Catalog\CatalogQueryService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(ProductIndexRequest $request, CatalogQueryService $catalogQuery): AnonymousResourceCollection
    {
        $products = $catalogQuery->products(
            $request->safe()->except('per_page'),
            $request->integer('per_page', 20)
        );

        return ProductResource::collection($products);
    }

    public function show(Product $product): ProductResource
    {
        $product->load(['category', 'variants.inventoryItem'])->loadCount('variants');

        return new ProductResource($product);
    }
}
