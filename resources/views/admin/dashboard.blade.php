@extends('layouts.admin', ['title' => 'Admin Dashboard | Ysabelle Retail'])

@section('content')
    <x-admin.page-header
        eyebrow="Operations"
        title="Company-grade retail overview"
        description="Track inventory, online orders, walk-in sales, and stock alerts from one operational dashboard."
    >
        <a href="{{ route('admin.pos.create') }}" class="ys-admin-button-primary">New walk-in sale</a>
        <a href="{{ route('admin.inventory.batch-imports.create') }}" class="ys-admin-button-secondary">Import stock</a>
    </x-admin.page-header>

    <section class="ys-admin-stat-grid">
        @foreach ([
            ['label' => 'Total Sales', 'value' => 'PHP '.number_format($metrics['total_sales'], 2)],
            ['label' => 'Online Sales', 'value' => 'PHP '.number_format($metrics['online_sales'], 2)],
            ['label' => 'Walk-in Sales', 'value' => 'PHP '.number_format($metrics['walk_in_sales'], 2)],
            ['label' => 'Total Products', 'value' => number_format($metrics['total_products'])],
            ['label' => 'Low Stock Items', 'value' => number_format($metrics['low_stock_items'])],
            ['label' => 'Out of Stock', 'value' => number_format($metrics['out_of_stock_items'])],
            ['label' => 'Pending Orders', 'value' => number_format($metrics['pending_orders'])],
            ['label' => 'Completed Orders', 'value' => number_format($metrics['completed_orders'])],
        ] as $card)
            <article class="ys-admin-stat-card" data-admin-panel>
                <p class="ys-admin-stat-label">{{ $card['label'] }}</p>
                <p class="ys-admin-stat-value">{{ $card['value'] }}</p>
            </article>
        @endforeach
    </section>

    <section class="ys-admin-kpi-grid">
        <article class="ys-admin-panel" data-admin-panel>
            <div class="ys-admin-panel-heading">
                <div>
                    <h2 class="ys-admin-panel-title">Sales Chart</h2>
                    <p class="ys-admin-subtle">Last 7 days of total sales volume.</p>
                </div>
            </div>

            <div class="ys-admin-chart">
                @php
                    $maxChartValue = max(1, $sales_chart->max('total'));
                @endphp

                @foreach ($sales_chart as $point)
                    <div class="space-y-3 text-center">
                        <div class="mx-auto flex h-44 w-full max-w-16 items-end justify-center rounded-[1.1rem] border border-white/7 bg-white/[0.02] px-2 py-2">
                            <div class="ys-admin-chart-bar w-full" style="height: {{ max(6, ($point['total'] / $maxChartValue) * 160) }}px"></div>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-ys-ivory/72">{{ $point['label'] }}</p>
                            <p class="mt-1 text-[0.72rem] text-ys-ivory/38">PHP {{ number_format($point['total'], 0) }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </article>

        <article class="ys-admin-panel" data-admin-panel>
            <div class="ys-admin-panel-heading">
                <div>
                    <h2 class="ys-admin-panel-title">Stock Movement Summary</h2>
                    <p class="ys-admin-subtle">Audited movements across all inventory sources.</p>
                </div>
            </div>

            <div class="space-y-3 pt-4">
                @foreach ($stock_movement_summary as $movement)
                    <div class="flex items-center justify-between rounded-[1rem] border border-white/7 bg-white/[0.03] px-4 py-3">
                        <div>
                            <p class="text-sm font-semibold text-ys-ivory">{{ $movement['label'] }}</p>
                            <p class="text-xs text-ys-ivory/40">{{ $movement['total_records'] }} record(s)</p>
                        </div>
                        <p class="text-sm font-semibold {{ $movement['total_quantity'] < 0 ? 'text-[#ffb1b1]' : 'text-[#9ae0b3]' }}">
                            {{ $movement['total_quantity'] > 0 ? '+' : '' }}{{ $movement['total_quantity'] }}
                        </p>
                    </div>
                @endforeach
            </div>
        </article>
    </section>

    <section class="grid gap-6 xl:grid-cols-3">
        <article class="ys-admin-panel xl:col-span-2" data-admin-panel>
            <div class="ys-admin-panel-heading">
                <div>
                    <h2 class="ys-admin-panel-title">Recent Online Orders</h2>
                    <p class="ys-admin-subtle">Latest storefront transactions requiring fulfillment attention.</p>
                </div>
                <a href="{{ route('admin.orders.index', ['source' => 'online']) }}" class="ys-admin-button-secondary">View orders</a>
            </div>

            <div class="ys-admin-table-wrap mt-4">
                <table class="ys-admin-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Status</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recent_orders as $order)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.orders.show', $order) }}" class="font-semibold text-ys-ivory">{{ $order->order_number }}</a>
                                    <p class="text-xs text-ys-ivory/38">{{ optional($order->placed_at)->format('M d, Y h:i A') }}</p>
                                </td>
                                <td>{{ $order->customer_name ?: 'Registered Customer' }}</td>
                                <td>
                                    <x-admin.status-pill :tone="$order->status === 'completed' ? 'success' : 'warning'">
                                        {{ $order->status }}
                                    </x-admin.status-pill>
                                </td>
                                <td>PHP {{ number_format((float) $order->grand_total, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="ys-admin-empty-panel">No online orders have been placed yet.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <article class="ys-admin-panel" data-admin-panel>
            <div class="ys-admin-panel-heading">
                <div>
                    <h2 class="ys-admin-panel-title">Inventory Alerts</h2>
                    <p class="ys-admin-subtle">Low stock and reorder reminders.</p>
                </div>
            </div>

            <div class="space-y-3 pt-4">
                @forelse ($inventory_alerts as $item)
                    <div class="rounded-[1rem] border border-white/7 bg-white/[0.03] px-4 py-3">
                        <p class="text-sm font-semibold text-ys-ivory">{{ $item->variant->product->name }}</p>
                        <p class="mt-1 text-xs text-ys-ivory/42">{{ $item->variant->sku }} · {{ $item->variant->name }}</p>
                        <div class="mt-3 flex items-center justify-between text-sm">
                            <span class="text-ys-ivory/55">On hand: {{ $item->quantity_on_hand }}</span>
                            <x-admin.status-pill tone="warning">Reorder at {{ $item->reorder_level }}</x-admin.status-pill>
                        </div>
                    </div>
                @empty
                    <div class="ys-admin-empty-panel">No low-stock alerts right now.</div>
                @endforelse
            </div>
        </article>
    </section>

    <section class="grid gap-6 xl:grid-cols-2">
        <article class="ys-admin-panel" data-admin-panel>
            <div class="ys-admin-panel-heading">
                <div>
                    <h2 class="ys-admin-panel-title">Recent Walk-in Sales</h2>
                    <p class="ys-admin-subtle">Latest point-of-sale receipts processed by admin staff.</p>
                </div>
            </div>

            <div class="space-y-3 pt-4">
                @forelse ($recent_walk_in_sales as $order)
                    <div class="rounded-[1rem] border border-white/7 bg-white/[0.03] px-4 py-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <a href="{{ route('admin.orders.show', $order) }}" class="text-sm font-semibold text-ys-ivory">{{ $order->order_number }}</a>
                                <p class="mt-1 text-xs text-ys-ivory/42">{{ $order->customer_name }} · {{ optional($order->placed_at)->format('M d, Y h:i A') }}</p>
                            </div>
                            <p class="text-sm font-semibold text-ys-gold">PHP {{ number_format((float) $order->grand_total, 2) }}</p>
                        </div>
                        <p class="mt-3 text-xs text-ys-ivory/45">Handled by {{ $order->handledBy?->name ?? 'Admin' }}</p>
                    </div>
                @empty
                    <div class="ys-admin-empty-panel">No walk-in sales yet.</div>
                @endforelse
            </div>
        </article>
    </section>
@endsection
