@extends('layouts.admin', ['title' => 'Batch Import | Ysabelle Retail'])

@section('content')
    <x-admin.page-header
        eyebrow="Inventory"
        title="Batch stock import"
        description="Upload CSV or Excel stock files, validate every row, and commit only after a clean preview."
    >
        <a href="{{ route('admin.inventory.batch-imports.template') }}" class="ys-admin-button-secondary">Download template</a>
    </x-admin.page-header>

    <form method="POST" action="{{ route('admin.inventory.batch-imports.preview') }}" enctype="multipart/form-data" class="space-y-6" data-admin-form>
        @csrf
        <section class="ys-admin-panel" data-admin-panel>
            <label class="ys-admin-field">
                <span class="ys-admin-label">CSV or Excel File</span>
                <input type="file" name="file" class="ys-admin-input" accept=".csv,.xlsx,.xls">
            </label>
            <p class="ys-admin-subtle mt-3">Required columns: `sku`, `product_name`, `variant`, `quantity`, `cost_price`, `supplier`, `notes`.</p>

            <div class="mt-5 ys-admin-inline-actions">
                <button type="submit" class="ys-admin-button-primary" data-loading-label="Parsing file...">Preview import</button>
            </div>
        </section>
    </form>

    @if ($preview)
        <section class="ys-admin-panel" data-admin-panel>
            <div class="ys-admin-panel-heading">
                <div>
                    <h2 class="ys-admin-panel-title">Preview Summary</h2>
                    <p class="ys-admin-subtle">{{ $preview['filename'] }}</p>
                </div>
                <div class="ys-admin-inline-actions text-sm text-ys-ivory/55">
                    <span>{{ $preview['summary']['total_rows'] }} total</span>
                    <span>{{ $preview['summary']['valid_rows'] }} valid</span>
                    <span>{{ $preview['summary']['invalid_rows'] }} invalid</span>
                </div>
            </div>

            <div class="ys-admin-table-wrap mt-5">
                <table class="ys-admin-table">
                    <thead>
                        <tr>
                            <th>Row</th>
                            <th>SKU</th>
                            <th>Quantity</th>
                            <th>Resolved Variant</th>
                            <th>Validation</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($preview['rows'] as $row)
                            <tr>
                                <td>{{ $row['row_number'] }}</td>
                                <td>{{ $row['values']['sku'] }}</td>
                                <td>{{ $row['values']['quantity'] }}</td>
                                <td>{{ $row['product_name'] ? "{$row['product_name']} · {$row['variant_name']}" : 'Not found' }}</td>
                                <td>
                                    @if ($row['errors'] === [])
                                        <x-admin.status-pill tone="success">Valid</x-admin.status-pill>
                                    @else
                                        <div class="space-y-1 text-sm text-[#ffcece]">
                                            @foreach ($row['errors'] as $error)
                                                <p>{{ $error }}</p>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <form method="POST" action="{{ route('admin.inventory.batch-imports.store') }}" class="mt-5" data-admin-form>
                @csrf
                <input type="hidden" name="preview_token" value="{{ $preview['token'] }}">
                <button type="submit" class="ys-admin-button-primary" data-loading-label="Importing stock..." @disabled($preview['summary']['invalid_rows'] > 0)>Commit import</button>
            </form>
        </section>
    @endif
@endsection
