<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Services\Admin\StockManagementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request, StockManagementService $stock): View
    {
        return view('admin.inventory.index', $stock->buildPageData($request));
    }
}
