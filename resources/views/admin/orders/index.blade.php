@extends('layouts.admin', ['title' => 'Orders | Ysabelle Retail'])

@section('content')
    <x-admin.page-header
        eyebrow="Orders"
        title="Orders and sales"
        description="Review online orders and walk-in receipts in one unified sales ledger."
    />

    <section class="ys-admin-panel" data-admin-panel>
        <form method="GET" class="ys-admin-filter-row">
            <input type="text" name="search" value="{{ $filters['search'] }}" class="ys-admin-input" placeholder="Search order number, customer, or phone">
            <select name="source" class="ys-admin-select">
                @foreach (['all' => 'All sources', 'online' => 'Online', 'walk_in' => 'Walk-in'] as $value => $label)
                    <option value="{{ $value }}" @selected($filters['source'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="status" class="ys-admin-select">
                @foreach (['all' => 'All statuses', 'pending' => 'Pending', 'completed' => 'Completed'] as $value => $label)
                    <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="ys-admin-button-secondary">Filter</button>
        </form>

        <div class="ys-admin-table-wrap mt-5">
            <table class="ys-admin-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Source</th>
                        <th>Customer</th>
                        <th>Payment</th>
                        <th>Total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td>
                                <p class="font-semibold text-ys-ivory">{{ $order->order_number }}</p>
                                <p class="text-xs text-ys-ivory/38">{{ optional($order->placed_at)->format('M d, Y h:i A') }}</p>
                            </td>
                            <td>
                                <x-admin.status-pill :tone="$order->source === 'walk_in' ? 'warning' : 'neutral'">
                                    {{ str($order->source)->headline() }}
                                </x-admin.status-pill>
                            </td>
                            <td>
                                <p>{{ $order->customer_name ?: 'Registered customer' }}</p>
                                <p class="text-xs text-ys-ivory/38">{{ $order->customer_phone ?: $order->customer_email ?: '—' }}</p>
                            </td>
                            <td>
                                <p>{{ strtoupper((string) $order->payment_method) }}</p>
                                <p class="text-xs text-ys-ivory/38">{{ ucfirst($order->payment_status) }}</p>
                            </td>
                            <td>PHP {{ number_format((float) $order->grand_total, 2) }}</td>
                            <td class="text-right">
                                <a href="{{ route('admin.orders.show', $order) }}" class="ys-admin-button-secondary">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="ys-admin-empty-panel">No orders matched the current filters.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-5">
            {{ $orders->links() }}
        </div>
    </section>
@endsection
