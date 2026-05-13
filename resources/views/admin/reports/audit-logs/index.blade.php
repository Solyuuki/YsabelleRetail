@extends('layouts.admin', ['title' => 'Audit Logs | Ysabelle Retail'])

@section('content')
    <x-admin.page-header
        eyebrow="Reports"
        title="Audit Logs"
        description="Review the persistent operational and compliance history captured in the admin audit trail."
    >
        <a href="{{ route('admin.reports.index') }}" class="ys-admin-button-secondary">Back to reports</a>
    </x-admin.page-header>

    <section class="ys-admin-panel" data-admin-panel>
        <form method="GET" action="{{ route('admin.reports.audit-logs.index') }}" class="ys-admin-grid-fields">
            <label class="ys-admin-field">
                <span class="ys-admin-label">Date From</span>
                <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="ys-admin-input">
            </label>

            <label class="ys-admin-field">
                <span class="ys-admin-label">Date To</span>
                <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="ys-admin-input">
            </label>

            <label class="ys-admin-field">
                <span class="ys-admin-label">Action Type</span>
                <select name="action" class="ys-admin-select">
                    <option value="">All actions</option>
                    @foreach ($lookups['actions'] as $value => $label)
                        <option value="{{ $value }}" @selected($filters['action'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="ys-admin-field">
                <span class="ys-admin-label">Actor</span>
                <select name="actor_id" class="ys-admin-select">
                    <option value="">All actors</option>
                    @foreach ($lookups['actors'] as $actor)
                        <option value="{{ $actor->id }}" @selected((string) $filters['actor_id'] === (string) $actor->id)>{{ $actor->name }} ({{ $actor->email }})</option>
                    @endforeach
                </select>
            </label>

            <label class="ys-admin-field">
                <span class="ys-admin-label">Entity Type</span>
                <select name="entity_type" class="ys-admin-select">
                    <option value="">All entities</option>
                    @foreach ($lookups['entity_types'] as $value => $label)
                        <option value="{{ $value }}" @selected($filters['entity_type'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <div class="ys-admin-inline-actions items-end">
                <button class="ys-admin-button-primary">Filter audit logs</button>
            </div>
        </form>
    </section>

    <section class="ys-admin-panel" data-admin-panel>
        <div class="ys-admin-panel-heading">
            <div>
                <h2 class="ys-admin-panel-title">{{ $dataset['title'] }}</h2>
                <p class="ys-admin-subtle">Persistent records from the `audit_logs` table, filtered in real time.</p>
            </div>
            <div class="ys-admin-inline-actions">
                <a href="{{ route('admin.reports.audit-logs.export', array_merge($filters, ['format' => 'csv'])) }}" class="ys-admin-button-secondary">Export CSV</a>
                <a href="{{ route('admin.reports.audit-logs.export', array_merge($filters, ['format' => 'xlsx'])) }}" class="ys-admin-button-secondary">Export XLSX</a>
                <a href="{{ route('admin.reports.audit-logs.export', array_merge($filters, ['format' => 'pdf'])) }}" class="ys-admin-button-primary">Export PDF</a>
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
                                <div class="ys-admin-empty-panel">No audit log records match the current filters.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-5">
            {{ $dataset['rows']->links('vendor.pagination.admin') }}
        </div>

        <div class="mt-5 grid gap-3 md:grid-cols-3">
            @foreach ($dataset['totals'] as $label => $value)
                <div class="rounded-[1rem] border border-white/7 bg-white/[0.03] px-4 py-4">
                    <p class="text-xs uppercase tracking-[0.24em] text-ys-ivory/38">{{ str($label)->headline() }}</p>
                    <p class="mt-2 text-lg font-semibold text-ys-ivory">{{ $value }}</p>
                </div>
            @endforeach
        </div>
    </section>
@endsection
