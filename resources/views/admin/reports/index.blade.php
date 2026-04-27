@extends('layouts.admin', ['title' => 'Reports | Ysabelle Retail'])

@section('content')
    <x-admin.page-header
        eyebrow="Reports"
        title="Operational reports"
        description="Filter sales, inventory, walk-in, and product performance reports, then export them in branded PDF or CSV formats."
    />

    <section class="ys-admin-panel" data-admin-panel>
        <form method="GET" action="{{ route('admin.reports.index') }}" class="ys-admin-grid-fields">
            <label class="ys-admin-field">
                <span class="ys-admin-label">Report</span>
                <select name="report" class="ys-admin-select">
                    @foreach ($reportOptions as $value => $label)
                        <option value="{{ $value }}" @selected($reportKey === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="ys-admin-field">
                <span class="ys-admin-label">Date From</span>
                <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="ys-admin-input">
            </label>

            <label class="ys-admin-field">
                <span class="ys-admin-label">Date To</span>
                <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="ys-admin-input">
            </label>

            <label class="ys-admin-field">
                <span class="ys-admin-label">Category</span>
                <select name="category_id" class="ys-admin-select">
                    <option value="">All categories</option>
                    @foreach ($lookups['categories'] as $category)
                        <option value="{{ $category->id }}" @selected($filters['category_id'] == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="ys-admin-field">
                <span class="ys-admin-label">Stock Status</span>
                <select name="stock_status" class="ys-admin-select">
                    @foreach (['all' => 'All', 'low' => 'Low stock', 'out' => 'Out of stock'] as $value => $label)
                        <option value="{{ $value }}" @selected($filters['stock_status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <div class="ys-admin-inline-actions items-end">
                <button class="ys-admin-button-primary">Generate report</button>
            </div>
        </form>
    </section>

    <section class="ys-admin-panel" data-admin-panel>
        <div class="ys-admin-panel-heading">
            <div>
                <h2 class="ys-admin-panel-title">{{ $dataset['title'] }}</h2>
                <p class="ys-admin-subtle">Generated {{ now()->format('M d, Y h:i A') }}</p>
            </div>
            <div class="ys-admin-inline-actions">
                <a href="{{ route('admin.reports.export', array_merge($filters, ['format' => 'csv'])) }}" class="ys-admin-button-secondary">Export CSV</a>
                <a href="{{ route('admin.reports.export', array_merge($filters, ['format' => 'pdf'])) }}" class="ys-admin-button-primary">Export PDF</a>
            </div>
        </div>

        <div class="ys-admin-table-wrap mt-5">
            <table class="ys-admin-table">
                <thead>
                    <tr>
                        @foreach ($dataset['columns'] as $column)
                            <th>{{ $column }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($dataset['rows'] as $row)
                        <tr>
                            @foreach ($row as $cell)
                                <td>{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($dataset['columns']) }}">
                                <div class="ys-admin-empty-panel">No records were found for the selected report filters.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-5 grid gap-3 md:grid-cols-3">
            @foreach ($dataset['totals'] as $label => $value)
                <div class="rounded-[1rem] border border-white/7 bg-white/[0.03] px-4 py-4">
                    <p class="text-xs uppercase tracking-[0.24em] text-ys-ivory/38">{{ str($label)->headline() }}</p>
                    <p class="mt-2 text-lg font-semibold text-ys-ivory">{{ is_float($value) ? 'PHP '.number_format($value, 2) : $value }}</p>
                </div>
            @endforeach
        </div>
    </section>
@endsection
