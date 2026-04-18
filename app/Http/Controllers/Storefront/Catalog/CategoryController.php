<?php

namespace App\Http\Controllers\Storefront\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Category;
use App\Services\Catalog\CatalogQueryService;
use Illuminate\Contracts\View\View;

class CategoryController extends Controller
{
    public function index(CatalogQueryService $catalogQuery): View
    {
        return view('storefront.catalog.categories.index', [
            'categories' => $catalogQuery->categories(),
        ]);
    }

    public function show(Category $category): View
    {
        $category->load(['products.variants.inventoryItem']);

        return view('storefront.catalog.categories.show', [
            'category' => $category,
        ]);
    }
}
