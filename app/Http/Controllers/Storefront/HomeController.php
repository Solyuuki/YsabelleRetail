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
        $heroProduct = $catalogQuery->heroProduct();
        $featuredProducts = $catalogQuery->showcaseProducts($heroProduct, 4);

        return view('storefront.home', [
            'metrics' => $dashboardMetrics->summary(),
            'heroProduct' => $heroProduct,
            'featuredProducts' => $featuredProducts,
        ]);
    }
}
