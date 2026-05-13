<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Reports\AuditLogFilterRequest;
use App\Services\Reports\AuditLogReportService;
use App\Services\Reports\ReportExportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(AuditLogFilterRequest $request, AuditLogReportService $auditLogs): View
    {
        $filters = $this->normalizedFilters($request->validated());

        return view('admin.reports.audit-logs.index', [
            'filters' => $filters,
            'lookups' => $auditLogs->filterLookups(),
            'dataset' => $auditLogs->build($filters, 15),
        ]);
    }

    public function export(
        AuditLogFilterRequest $request,
        AuditLogReportService $auditLogs,
        ReportExportService $exports,
    ): StreamedResponse|Response {
        $filters = $this->normalizedFilters($request->validated());
        $dataset = $auditLogs->build($filters);
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
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
            'action' => $filters['action'] ?? null,
            'actor_id' => $filters['actor_id'] ?? null,
            'entity_type' => $filters['entity_type'] ?? null,
            'format' => $filters['format'] ?? 'csv',
        ];
    }
}
