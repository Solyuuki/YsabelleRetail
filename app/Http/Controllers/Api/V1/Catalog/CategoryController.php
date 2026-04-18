<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Catalog\CategoryIndexRequest;
use App\Http\Resources\Catalog\CategoryResource;
use App\Models\Catalog\Category;
use App\Services\Catalog\CatalogQueryService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function index(CategoryIndexRequest $request, CatalogQueryService $catalogQuery): AnonymousResourceCollection
    {
        $categories = $catalogQuery->categories($request->integer('per_page', 20));

        return CategoryResource::collection($categories);
    }

    public function show(Category $category): CategoryResource
    {
        $category->loadCount('products');

        return new CategoryResource($category);
    }
}
