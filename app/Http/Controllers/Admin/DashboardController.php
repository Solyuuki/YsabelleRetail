<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardMetricsService;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(DashboardMetricsService $dashboardMetrics): View
    {
        return view('admin.dashboard', [
            'metrics' => $dashboardMetrics->summary(),
        ]);
    }
}
