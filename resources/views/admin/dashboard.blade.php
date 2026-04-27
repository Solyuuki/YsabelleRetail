@extends('layouts.admin', ['title' => 'Admin Dashboard | Ysabelle Retail'])

@section('content')
    <x-admin.page-header
        eyebrow="Operations"
        title="E-commerce control center"
        description="Sales, stock, and alerts in one clean operating view."
    >
        <a href="{{ route('admin.pos.create') }}" class="ys-admin-button-primary">New sale</a>
        <a href="{{ route('admin.inventory.index', ['tab' => 'add-stock']) }}" class="ys-admin-button-secondary">Add stock</a>
        <a href="{{ route('admin.inventory.index', ['tab' => 'batch-import']) }}" class="ys-admin-button-secondary">Import stock</a>
    </x-admin.page-header>

    <section class="ys-admin-stat-grid">
        @foreach ([
            ['label' => 'Total Sales', 'value' => 'PHP '.number_format($metrics['total_sales'], 2), 'meta' => number_format($metrics['total_orders']).' orders'],
            ['label' => 'Online Sales', 'value' => 'PHP '.number_format($metrics['online_sales'], 2), 'meta' => 'Storefront'],
            ['label' => 'Walk-in Sales', 'value' => 'PHP '.number_format($metrics['walk_in_sales'], 2), 'meta' => 'Counter sales'],
            ['label' => 'Orders', 'value' => number_format($metrics['total_orders']), 'meta' => number_format($metrics['pending_orders']).' pending'],
            ['label' => 'Products', 'value' => number_format($metrics['total_products']), 'meta' => 'Tracked catalog'],
            ['label' => 'Low Stock', 'value' => number_format($metrics['low_stock_items']), 'meta' => 'Needs review'],
            ['label' => 'Out of Stock', 'value' => number_format($metrics['out_of_stock_items']), 'meta' => 'Immediate action'],
        ] as $card)
            <article class="ys-admin-stat-card" data-admin-panel>
                <p class="ys-admin-stat-label">{{ $card['label'] }}</p>
                <p class="ys-admin-stat-value">{{ $card['value'] }}</p>
                <p class="ys-admin-stat-meta">{{ $card['meta'] }}</p>
            </article>
        @endforeach
    </section>

    <section class="ys-admin-kpi-grid">
        <article class="ys-admin-panel" data-admin-panel>
            <div class="ys-admin-panel-heading">
                <div>
                    <h2 class="ys-admin-panel-title">Sales Trend</h2>
                    <p class="ys-admin-subtle">Last 7 days across online and walk-in.</p>
                </div>
            </div>

            @php
                $chartCount = max(1, $sales_chart->count());
                $chartWidth = 720;
                $chartHeight = 220;
                $maxChartValue = max(1, $sales_chart->max('total'));
                $step = $chartCount > 1 ? $chartWidth / ($chartCount - 1) : $chartWidth;
                $buildPoints = function (string $key) use ($sales_chart, $chartHeight, $step, $maxChartValue): string {
                    return $sales_chart->values()->map(function (array $point, int $index) use ($key, $chartHeight, $step, $maxChartValue): string {
                        $x = $index * $step;
                        $y = $chartHeight - (($point[$key] / $maxChartValue) * ($chartHeight - 18)) - 10;

                        return number_format($x, 2, '.', '').','.number_format($y, 2, '.', '');
                    })->implode(' ');
                };
                $totalSales = (float) $sales_chart->sum('total');
            @endphp

            @if ($totalSales <= 0)
                <div class="ys-admin-empty-panel mt-4">No sales data yet for the last 7 days.</div>
            @else
                <div class="ys-admin-chart-shell mt-5">
                    <div class="ys-admin-legend">
                        <span class="ys-admin-legend-item"><i class="is-total"></i>Total</span>
                        <span class="ys-admin-legend-item"><i class="is-online"></i>Online</span>
                        <span class="ys-admin-legend-item"><i class="is-walkin"></i>Walk-in</span>
                    </div>

                    <div class="ys-admin-chart-grid">
                        <svg viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" class="ys-admin-chart-svg" preserveAspectRatio="none" aria-hidden="true">
                            @foreach ([0.25, 0.5, 0.75] as $line)
                                <line x1="0" y1="{{ $chartHeight * $line }}" x2="{{ $chartWidth }}" y2="{{ $chartHeight * $line }}" class="ys-admin-chart-guide" />
                            @endforeach

                            <polyline points="{{ $buildPoints('walk_in_total') }}" class="ys-admin-chart-line ys-admin-chart-line-walkin" />
                            <polyline points="{{ $buildPoints('online_total') }}" class="ys-admin-chart-line ys-admin-chart-line-online" />
                            <polyline points="{{ $buildPoints('total') }}" class="ys-admin-chart-line ys-admin-chart-line-total" />
                        </svg>
                    </div>

                    <div class="ys-admin-chart-labels">
                        @foreach ($sales_chart as $point)
                            <div>
                                <p>{{ $point['label'] }}</p>
                                <span>PHP {{ number_format($point['total'], 0) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </article>

        <article class="ys-admin-panel" data-admin-panel>
            <div class="ys-admin-panel-heading">
                <div>
                    <h2 class="ys-admin-panel-title">Stock Flow</h2>
                    <p class="ys-admin-subtle">Shared inventory audit across all channels.</p>
                </div>
                <a href="{{ route('admin.inventory.index', ['tab' => 'movements']) }}" class="ys-admin-button-secondary">View movements</a>
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
                    <h2 class="ys-admin-panel-title">Recent Sales</h2>
                    <p class="ys-admin-subtle">Latest online orders and walk-in receipts.</p>
                </div>
                <a href="{{ route('admin.orders.index') }}" class="ys-admin-button-secondary">View sales</a>
            </div>

            <div class="ys-admin-table-wrap mt-4">
                <table class="ys-admin-table">
                    <thead>
                        <tr>
                            <th>Sale</th>
                            <th>Source</th>
                            <th>Customer</th>
                            <th>Status</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recent_sales as $order)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.orders.show', $order) }}" class="font-semibold text-ys-ivory">{{ $order->order_number }}</a>
                                    <p class="text-xs text-ys-ivory/38">{{ optional($order->placed_at)->format('M d, Y h:i A') }}</p>
                                </td>
                                <td>
                                    <x-admin.status-pill :tone="$order->source === 'walk_in' ? 'warning' : 'neutral'">
                                        {{ str($order->source)->headline() }}
                                    </x-admin.status-pill>
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
                                <td colspan="5">
                                    <div class="ys-admin-empty-panel">No recent sales yet.</div>
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
                    <p class="ys-admin-subtle">Items that need stock attention.</p>
                </div>
                <a href="{{ route('admin.inventory.index', ['tab' => 'inventory', 'status' => 'low']) }}" class="ys-admin-button-secondary">Review stock</a>
            </div>

            <div class="space-y-3 pt-4">
                @forelse ($inventory_alerts as $item)
                    <div class="rounded-[1rem] border border-white/7 bg-white/[0.03] px-4 py-3">
                        <p class="text-sm font-semibold text-ys-ivory">{{ $item->variant->product->name }}</p>
                        <p class="mt-1 text-xs text-ys-ivory/42">{{ $item->variant->sku }} / {{ $item->variant->name }}</p>
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

    <section class="ys-admin-panel mt-6" data-admin-panel>
        <div class="ys-admin-panel-heading">
            <div>
                <h2 class="ys-admin-panel-title">Live Activity</h2>
                <p class="ys-admin-subtle">Sales and inventory alerts refresh automatically without a full page reload.</p>
            </div>
            <div class="rounded-full border border-white/10 bg-white/[0.03] px-3 py-2 text-xs uppercase tracking-[0.24em] text-ys-ivory/48" data-admin-live-status>
                Live via polling
            </div>
        </div>

        <div class="mt-5 space-y-3" data-admin-live-feed-list>
            @forelse ($live_activity as $activity)
                <div class="rounded-[1rem] border border-white/7 bg-white/[0.03] px-4 py-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-ys-ivory">{{ $activity['title'] }}</p>
                            <p class="mt-1 text-sm leading-6 text-ys-ivory/58">{{ $activity['message'] }}</p>
                        </div>
                        <span class="shrink-0 text-xs uppercase tracking-[0.2em] text-ys-ivory/36">{{ $activity['timestamp'] }}</span>
                    </div>
                </div>
            @empty
                <div class="ys-admin-empty-panel">Live activity will appear here once sales or stock updates happen.</div>
            @endforelse
        </div>
    </section>
@endsection
