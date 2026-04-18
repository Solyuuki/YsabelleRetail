<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Catalog\CatalogQueryService;
use App\Services\Dashboard\DashboardMetricsService;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(
        DashboardMetricsService $dashboardMetrics,
        CatalogQueryService $catalogQuery
    ): View {
        return view('storefront.home', [
            'metrics' => $dashboardMetrics->summary(),
            'featuredProducts' => $catalogQuery->featuredProducts(),
            'featuredCategories' => $catalogQuery->navigationCategories(),
        ]);
    }
}
