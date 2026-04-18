<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Controller;
use App\Services\Catalog\CatalogQueryService;
use Illuminate\Contracts\View\View;

class CategoryController extends Controller
{
    public function index(CatalogQueryService $catalogQuery): View
    {
        return view('admin.catalog.categories.index', [
            'categories' => $catalogQuery->categories(),
        ]);
    }
}
