<?php

namespace App\Http\Controllers\Storefront\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\Catalog\ProductBrowseRequest;
use App\Models\Catalog\Product;
use App\Services\Catalog\CatalogQueryService;
use Illuminate\Contracts\View\View;

class ProductController extends Controller
{
    public function index(ProductBrowseRequest $request, CatalogQueryService $catalogQuery): View
    {
        return view('storefront.catalog.products.index', [
            'products' => $catalogQuery->products($request->validated()),
            'filters' => $request->validated(),
        ]);
    }

    public function show(Product $product): View
    {
        $product->load(['category', 'variants.inventoryItem']);

        return view('storefront.catalog.products.show', [
            'product' => $product,
        ]);
    }
}
