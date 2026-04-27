<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Reports\ReportFilterRequest;
use App\Services\Reports\ReportExportService;
use App\Services\Reports\ReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(ReportFilterRequest $request, ReportService $reports): View
    {
        $filters = $this->normalizedFilters($request->validated());
        $reportKey = $filters['report'];
        $dataset = $reports->build($reportKey, $filters, 15);

        return view('admin.reports.index', [
            'reportKey' => $reportKey,
            'filters' => $filters,
            'lookups' => $reports->filterLookups(),
            'dataset' => $dataset,
            'reportOptions' => config('admin.reports'),
        ]);
    }

    public function export(
        ReportFilterRequest $request,
        ReportService $reports,
        ReportExportService $exports,
    ): StreamedResponse|Response {
        $filters = $this->normalizedFilters($request->validated());
        $dataset = $reports->build($filters['report'], $filters);
        $generatedBy = $request->user()?->email ?? 'admin@ysabelle.store';

        return match ($filters['format']) {
            'pdf' => $exports->pdf($dataset, $filters, $generatedBy),
            'xlsx' => $exports->xlsx($dataset, $filters, $generatedBy),
            default => $exports->csv($dataset, $filters, $generatedBy),
        };
    }

    private function normalizedFilters(array $filters): array
    {
        return [
            'report' => $filters['report'] ?? 'sales',
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
            'category_id' => $filters['category_id'] ?? null,
            'stock_status' => $filters['stock_status'] ?? 'all',
            'format' => $filters['format'] ?? 'csv',
        ];
    }
}
