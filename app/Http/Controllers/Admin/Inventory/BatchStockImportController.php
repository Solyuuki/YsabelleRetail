<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Inventory\BatchStockImportCommitRequest;
use App\Http\Requests\Admin\Inventory\BatchStockImportUploadRequest;
use App\Services\Admin\StockManagementService;
use App\Services\Inventory\BatchStockImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BatchStockImportController extends Controller
{
    public function create(Request $request, StockManagementService $stock): View
    {
        return view('admin.inventory.index', $stock->buildPageData($request, [
            'activeTab' => 'batch-import',
            'preview' => session('inventory_import_preview'),
        ]));
    }

    public function preview(
        BatchStockImportUploadRequest $request,
        BatchStockImportService $imports,
    ): RedirectResponse {
        $preview = $imports->preview($request->file('file'));
        session(['inventory_import_preview' => $preview]);

        return redirect()
            ->route('admin.inventory.batch-imports.create', ['tab' => 'batch-import'])
            ->with('toast', [
                'type' => 'success',
                'title' => 'Import preview ready',
                'message' => 'Review the parsed rows before committing stock updates.',
            ]);
    }

    public function store(
        BatchStockImportCommitRequest $request,
        BatchStockImportService $imports,
    ): RedirectResponse {
        $preview = session('inventory_import_preview');

        abort_unless(($preview['token'] ?? null) === $request->string('preview_token')->toString(), 422);

        $batch = $imports->commit($preview, $request->user());
        session()->forget('inventory_import_preview');

        return redirect()
            ->route('admin.inventory.index', ['tab' => 'movements'])
            ->with('toast', [
                'type' => 'success',
                'title' => 'Stock import completed',
                'message' => "{$batch->imported_rows} inventory row(s) were imported successfully.",
            ]);
    }

    public function template(): BinaryFileResponse
    {
        $path = storage_path('app/public/admin-stock-import-template.csv');

        if (! file_exists($path)) {
            file_put_contents($path, "sku,product_name,variant,quantity,cost_price,supplier,notes\nYSV-000001,Aurum Runner,Size 9,10,2499.00,Local Supplier,Initial stock\n");
        }

        return response()->download($path, 'ysabelle-stock-import-template.csv');
    }
}
