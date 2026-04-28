@extends('layouts.admin', ['title' => 'Admin Dashboard | Ysabelle Retail'])

@section('content')
    @php
        $metricCards = [
            [
                'label' => 'Total Sales',
                'value' => $metrics['total_sales'],
                'display' => 'PHP '.number_format($metrics['total_sales'], 2),
                'meta' => number_format($metrics['total_orders']).' orders captured',
                'icon' => 'spark',
                'format' => 'currency',
                'is_primary' => true,
            ],
            [
                'label' => 'Online Sales',
                'value' => $metrics['online_sales'],
                'display' => 'PHP '.number_format($metrics['online_sales'], 2),
                'meta' => 'Storefront revenue stream',
                'icon' => 'globe',
                'format' => 'currency',
                'is_primary' => false,
            ],
            [
                'label' => 'Walk-in Sales',
                'value' => $metrics['walk_in_sales'],
                'display' => 'PHP '.number_format($metrics['walk_in_sales'], 2),
                'meta' => 'Counter and in-store purchases',
                'icon' => 'pos',
                'format' => 'currency',
                'is_primary' => false,
            ],
            [
                'label' => 'Products',
                'value' => $metrics['total_products'],
                'display' => number_format($metrics['total_products']),
                'meta' => 'Tracked across the catalog',
                'icon' => 'products',
                'format' => 'integer',
                'is_primary' => false,
            ],
            [
                'label' => 'Pending Orders',
                'value' => $metrics['pending_orders'],
                'display' => number_format($metrics['pending_orders']),
                'meta' => 'Needs review or fulfillment',
                'icon' => 'clock',
                'format' => 'integer',
                'is_primary' => false,
            ],
            [
                'label' => 'Completed',
                'value' => $metrics['completed_orders'],
                'display' => number_format($metrics['completed_orders']),
                'meta' => 'Closed successfully',
                'icon' => 'check-circle',
                'format' => 'integer',
                'is_primary' => false,
            ],
            [
                'label' => 'Low Stock',
                'value' => $metrics['low_stock_items'],
                'display' => number_format($metrics['low_stock_items']),
                'meta' => 'Variants needing replenishment',
                'icon' => 'alert',
                'format' => 'integer',
                'is_primary' => false,
            ],
            [
                'label' => 'Out of Stock',
                'value' => $metrics['out_of_stock_items'],
                'display' => number_format($metrics['out_of_stock_items']),
                'meta' => 'Immediate attention',
                'icon' => 'slash-circle',
                'format' => 'integer',
                'is_primary' => false,
            ],
        ];

        $chartPoints = $sales_chart->values();
        $chartHasData = $chartPoints->sum('total') > 0;
        $chartWidth = 920;
        $chartHeight = 300;
        $chartFloor = $chartHeight - 14;
        $maxChartValue = max(1, (float) $chartPoints->max('total'));
        $step = $chartPoints->count() > 1 ? $chartWidth / ($chartPoints->count() - 1) : $chartWidth;
        $mapSeries = function (string $key) use ($chartPoints, $chartFloor, $maxChartValue, $step): \Illuminate\Support\Collection {
            return $chartPoints->map(function (array $point, int $index) use ($key, $chartFloor, $maxChartValue, $step): array {
                $x = $index * $step;
                $y = $chartFloor - (($point[$key] / $maxChartValue) * 236);

                return [
                    'x' => (float) number_format($x, 2, '.', ''),
                    'y' => (float) number_format($y, 2, '.', ''),
                    'value' => (float) $point[$key],
                    'label' => $point['label'],
                    'orders_count' => $point['orders_count'],
                ];
            });
        };
        $buildPath = function (\Illuminate\Support\Collection $points): string {
            if ($points->isEmpty()) {
                return '';
            }

            $path = 'M '.number_format($points->first()['x'], 2, '.', '').' '.number_format($points->first()['y'], 2, '.', '');

            for ($index = 0; $index < $points->count() - 1; $index++) {
                $current = $points->get($index);
                $next = $points->get($index + 1);
                $controlX = number_format(($current['x'] + $next['x']) / 2, 2, '.', '');

                $path .= ' C '.$controlX.' '.number_format($current['y'], 2, '.', '').', '.$controlX.' '.number_format($next['y'], 2, '.', '').', '.number_format($next['x'], 2, '.', '').' '.number_format($next['y'], 2, '.', '');
            }

            return $path;
        };
        $buildAreaPath = function (\Illuminate\Support\Collection $points) use ($buildPath, $chartFloor): string {
            if ($points->isEmpty()) {
                return '';
            }

            $line = $buildPath($points);
            $first = $points->first();
            $last = $points->last();

            return $line
                .' L '.number_format($last['x'], 2, '.', '').' '.number_format($chartFloor, 2, '.', '')
                .' L '.number_format($first['x'], 2, '.', '').' '.number_format($chartFloor, 2, '.', '')
                .' Z';
        };
        $totalSeries = $mapSeries('total');
        $onlineSeries = $mapSeries('online_total');
        $walkInSeries = $mapSeries('walk_in_total');
        $chartTotal = (float) $chartPoints->sum('total');
        $chartOnlineTotal = (float) $chartPoints->sum('online_total');
        $chartWalkInTotal = (float) $chartPoints->sum('walk_in_total');
        $chartPeak = $chartPoints->sortByDesc('total')->first();
        $onlineShare = $chartTotal > 0 ? round(($chartOnlineTotal / $chartTotal) * 100) : 0;
        $formatCompactCurrency = function (float $amount): string {
            if ($amount >= 1000) {
                return 'PHP '.rtrim(rtrim(number_format($amount / 1000, 1), '0'), '.').'k';
            }

            return 'PHP '.number_format($amount, 0);
        };
        $chartSummary = [
            ['label' => '7-day Revenue', 'value' => $formatCompactCurrency($chartTotal), 'meta' => number_format($chartPoints->sum('orders_count')).' orders'],
            ['label' => 'Online Share', 'value' => $onlineShare.'%', 'meta' => (100 - $onlineShare).'% walk-in'],
            ['label' => 'Peak Day', 'value' => $chartPeak ? $chartPeak['label'] : 'N/A', 'meta' => $chartPeak ? $formatCompactCurrency($chartPeak['total']) : 'No activity'],
        ];
        $chartTicks = collect(range(0, 4))->map(function (int $index) use ($maxChartValue, $chartFloor): array {
            $value = $maxChartValue - (($maxChartValue / 4) * $index);
            $y = $chartFloor - (($value / max($maxChartValue, 1)) * 236);

            return [
                'label' => 'PHP '.number_format($value, 0),
                'y' => $y,
            ];
        });
    @endphp

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
        @foreach ($metricCards as $card)
            <article class="ys-admin-stat-card {{ $card['is_primary'] ? 'is-primary' : '' }}" data-admin-panel>
                <div class="ys-admin-stat-topline">
                    <div>
                        <p class="ys-admin-stat-label">{{ $card['label'] }}</p>
                        <p
                            class="ys-admin-stat-value {{ $card['is_primary'] ? 'is-primary' : '' }}"
                            data-countup
                            data-countup-value="{{ $card['value'] }}"
                            data-countup-format="{{ $card['format'] }}"
                        >
                            {{ $card['display'] }}
                        </p>
                    </div>
                    <span class="ys-admin-stat-icon {{ $card['is_primary'] ? 'is-primary' : '' }}">
                        <x-admin.icon :name="$card['icon']" class="h-5 w-5" />
                    </span>
                </div>

                <div class="ys-admin-stat-bottomline">
                    <span class="ys-admin-stat-meta">{{ $card['meta'] }}</span>
                    @if ($card['is_primary'])
                        <span class="ys-admin-stat-badge">Primary KPI</span>
                    @endif
                </div>
            </article>
        @endforeach
    </section>

    <section class="ys-admin-kpi-grid">
        <article class="ys-admin-panel" data-admin-panel>
            <div class="ys-admin-panel-heading">
                <div>
                    <h2 class="ys-admin-panel-title">Sales Trend</h2>
                    <p class="ys-admin-subtle">Last 7 days across online and walk-in sales with a healthier revenue pattern.</p>
                </div>
                <div class="ys-admin-legend">
                    <span class="ys-admin-legend-item"><i class="is-total"></i>Total</span>
                    <span class="ys-admin-legend-item"><i class="is-online"></i>Online</span>
                    <span class="ys-admin-legend-item"><i class="is-walkin"></i>Walk-in</span>
                </div>
            </div>

            @if ($chartHasData)
                <div class="ys-admin-chart-shell" data-admin-sales-chart>
                    <div class="ys-admin-chart-summary">
                        @foreach ($chartSummary as $summary)
                            <div class="ys-admin-chart-summary-card">
                                <span class="ys-admin-chart-summary-label">{{ $summary['label'] }}</span>
                                <strong class="ys-admin-chart-summary-value">{{ $summary['value'] }}</strong>
                                <span class="ys-admin-chart-summary-meta">{{ $summary['meta'] }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div class="ys-admin-chart-grid">
                        <div class="ys-admin-chart-surface" data-chart-surface>
                            <div class="ys-admin-chart-axis">
                                @foreach ($chartTicks as $tick)
                                    <span style="top: {{ $tick['y'] }}px;">{{ $tick['label'] }}</span>
                                @endforeach
                            </div>

                            <svg viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" class="ys-admin-chart-svg" preserveAspectRatio="none" aria-hidden="true">
                                <defs>
                                    <linearGradient id="ys-admin-chart-area-total" x1="0" x2="0" y1="0" y2="1">
                                        <stop offset="0%" stop-color="#d4a63b" stop-opacity="0.3" />
                                        <stop offset="100%" stop-color="#d4a63b" stop-opacity="0.01" />
                                    </linearGradient>
                                    <linearGradient id="ys-admin-chart-area-online" x1="0" x2="0" y1="0" y2="1">
                                        <stop offset="0%" stop-color="#f5ebd6" stop-opacity="0.18" />
                                        <stop offset="100%" stop-color="#f5ebd6" stop-opacity="0" />
                                    </linearGradient>
                                    <linearGradient id="ys-admin-chart-area-walkin" x1="0" x2="0" y1="0" y2="1">
                                        <stop offset="0%" stop-color="#9ae0b3" stop-opacity="0.18" />
                                        <stop offset="100%" stop-color="#9ae0b3" stop-opacity="0" />
                                    </linearGradient>
                                </defs>

                                @foreach ($chartTicks as $tick)
                                    <line x1="0" y1="{{ $tick['y'] }}" x2="{{ $chartWidth }}" y2="{{ $tick['y'] }}" class="ys-admin-chart-guide" />
                                @endforeach

                                <path d="{{ $buildAreaPath($totalSeries) }}" class="ys-admin-chart-area ys-admin-chart-area-total" />
                                <path d="{{ $buildAreaPath($onlineSeries) }}" class="ys-admin-chart-area ys-admin-chart-area-online" />
                                <path d="{{ $buildAreaPath($walkInSeries) }}" class="ys-admin-chart-area ys-admin-chart-area-walkin" />

                                <path d="{{ $buildPath($walkInSeries) }}" class="ys-admin-chart-line ys-admin-chart-line-walkin" />
                                <path d="{{ $buildPath($onlineSeries) }}" class="ys-admin-chart-line ys-admin-chart-line-online" />
                                <path d="{{ $buildPath($totalSeries) }}" class="ys-admin-chart-line ys-admin-chart-line-total" />

                                @foreach ($chartPoints as $index => $point)
                                    <circle cx="{{ $onlineSeries[$index]['x'] }}" cy="{{ $onlineSeries[$index]['y'] }}" r="3" class="ys-admin-chart-dot ys-admin-chart-dot-online" />
                                    <circle cx="{{ $walkInSeries[$index]['x'] }}" cy="{{ $walkInSeries[$index]['y'] }}" r="3" class="ys-admin-chart-dot ys-admin-chart-dot-walkin" />
                                    <circle cx="{{ $totalSeries[$index]['x'] }}" cy="{{ $totalSeries[$index]['y'] }}" r="4.25" class="ys-admin-chart-dot ys-admin-chart-dot-total" />
                                    <circle
                                        cx="{{ $totalSeries[$index]['x'] }}"
                                        cy="{{ $totalSeries[$index]['y'] }}"
                                        r="16"
                                        class="ys-admin-chart-hit-area"
                                        tabindex="0"
                                        data-chart-point
                                        data-chart-label="{{ $point['label'] }}"
                                        data-chart-total="{{ number_format($point['total'], 2, '.', '') }}"
                                        data-chart-online="{{ number_format($point['online_total'], 2, '.', '') }}"
                                        data-chart-walkin="{{ number_format($point['walk_in_total'], 2, '.', '') }}"
                                        data-chart-orders="{{ $point['orders_count'] }}"
                                        data-chart-x="{{ $totalSeries[$index]['x'] }}"
                                        data-chart-y="{{ $totalSeries[$index]['y'] }}"
                                    />
                                @endforeach
                            </svg>
                        </div>

                        <div class="ys-admin-chart-tooltip" data-chart-tooltip hidden aria-hidden="true"></div>
                    </div>

                    <div class="ys-admin-chart-labels">
                        @foreach ($chartPoints as $point)
                            <div class="ys-admin-chart-label-card">
                                <p>{{ $point['label'] }}</p>
                                <span>{{ $formatCompactCurrency($point['total']) }}</span>
                                <small>{{ $point['orders_count'] }} order(s)</small>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="ys-admin-empty-state mt-5">
                    <span class="ys-admin-empty-state-icon">
                        <x-admin.icon name="trend" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="ys-admin-empty-state-title">Sales trend will appear once orders start flowing.</p>
                        <p class="ys-admin-empty-state-copy">As soon as online or walk-in sales are recorded, this chart will surface spikes, channel split, and recent pace.</p>
                    </div>
                </div>
            @endif
        </article>

        <article class="ys-admin-panel" data-admin-panel>
            <div class="ys-admin-panel-heading">
                <div>
                    <h2 class="ys-admin-panel-title">Stock Flow</h2>
                    <p class="ys-admin-subtle">Shared inventory movement across storefront and counter sales.</p>
                </div>
                <a href="{{ route('admin.inventory.index', ['tab' => 'movements']) }}" class="ys-admin-button-secondary">View movements</a>
            </div>

            <div class="ys-admin-flow-list">
                @foreach ($stock_movement_summary as $movement)
                    <div class="ys-admin-flow-item">
                        <span class="ys-admin-flow-icon is-{{ $movement['direction'] }}">
                            <x-admin.icon :name="$movement['icon']" class="h-4 w-4" />
                        </span>

                        <div class="ys-admin-flow-copy">
                            <div class="ys-admin-flow-title-row">
                                <p class="ys-admin-flow-title">{{ $movement['label'] }}</p>
                                <span class="ys-admin-flow-records">{{ $movement['total_records'] }} record(s)</span>
                            </div>
                            <p class="ys-admin-flow-caption">{{ $movement['caption'] }}</p>
                        </div>

                        <p class="ys-admin-flow-quantity is-{{ $movement['direction'] }}">
                            {{ $movement['total_quantity'] > 0 ? '+' : '' }}{{ $movement['total_quantity'] }}
                        </p>
                    </div>
                @endforeach
            </div>
        </article>
    </section>

    <section class="grid gap-6 xl:grid-cols-3">
        <article class="ys-admin-panel ys-admin-feed-panel" data-admin-panel>
            <div class="ys-admin-panel-heading">
                <div>
                    <h2 class="ys-admin-panel-title">Recent Orders</h2>
                    <p class="ys-admin-subtle">Latest storefront checkouts needing visibility.</p>
                </div>
                <a href="{{ route('admin.orders.index', ['source' => 'online']) }}" class="ys-admin-button-secondary">View all</a>
            </div>

            <div class="ys-admin-feed-list">
                @forelse ($recent_orders as $order)
                    <div class="ys-admin-feed-item">
                        <div class="ys-admin-feed-item-top">
                            <div>
                                @if ($order['url'])
                                    <a href="{{ $order['url'] }}" class="ys-admin-feed-title">{{ $order['reference'] }}</a>
                                @else
                                    <p class="ys-admin-feed-title">{{ $order['reference'] }}</p>
                                @endif
                                <p class="ys-admin-feed-copy">{{ $order['customer'] }}</p>
                            </div>

                            <div class="text-right">
                                <p class="ys-admin-feed-amount">PHP {{ number_format($order['amount'], 2) }}</p>
                                <x-admin.status-pill :tone="$order['status_tone']">{{ $order['status'] }}</x-admin.status-pill>
                            </div>
                        </div>

                        <div class="ys-admin-feed-item-bottom">
                            <span>{{ $order['note'] }}</span>
                            <span>{{ $order['placed_at'] }}</span>
                        </div>
                    </div>
                @empty
                    <div class="ys-admin-empty-state is-compact">
                        <span class="ys-admin-empty-state-icon">
                            <x-admin.icon name="orders" class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="ys-admin-empty-state-title">No recent online orders yet.</p>
                            <p class="ys-admin-empty-state-copy">New storefront purchases will appear here with their value and fulfillment status.</p>
                        </div>
                    </div>
                @endforelse
            </div>
        </article>

        <article class="ys-admin-panel ys-admin-feed-panel" data-admin-panel>
            <div class="ys-admin-panel-heading">
                <div>
                    <h2 class="ys-admin-panel-title">Recent Walk-ins</h2>
                    <p class="ys-admin-subtle">Counter transactions recorded from the POS.</p>
                </div>
                <a href="{{ route('admin.orders.index', ['source' => 'walk_in']) }}" class="ys-admin-button-secondary">View all</a>
            </div>

            <div class="ys-admin-feed-list">
                @forelse ($recent_walk_ins as $sale)
                    <div class="ys-admin-feed-item">
                        <div class="ys-admin-feed-item-top">
                            <div>
                                @if ($sale['url'])
                                    <a href="{{ $sale['url'] }}" class="ys-admin-feed-title">{{ $sale['reference'] }}</a>
                                @else
                                    <p class="ys-admin-feed-title">{{ $sale['reference'] }}</p>
                                @endif
                                <p class="ys-admin-feed-copy">{{ $sale['customer'] }}</p>
                            </div>

                            <div class="text-right">
                                <p class="ys-admin-feed-amount">PHP {{ number_format($sale['amount'], 2) }}</p>
                                <x-admin.status-pill :tone="$sale['status_tone']">{{ $sale['status'] }}</x-admin.status-pill>
                            </div>
                        </div>

                        <div class="ys-admin-feed-item-bottom">
                            <span>{{ $sale['note'] }}</span>
                            <span>{{ $sale['placed_at'] }}</span>
                        </div>
                    </div>
                @empty
                    <div class="ys-admin-empty-state is-compact">
                        <span class="ys-admin-empty-state-icon">
                            <x-admin.icon name="pos" class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="ys-admin-empty-state-title">No walk-in sales recorded yet.</p>
                            <p class="ys-admin-empty-state-copy">POS activity will populate here once the counter starts logging transactions.</p>
                        </div>
                    </div>
                @endforelse
            </div>
        </article>

        <article class="ys-admin-panel ys-admin-feed-panel" data-admin-panel>
            <div class="ys-admin-panel-heading">
                <div>
                    <h2 class="ys-admin-panel-title">Inventory Alerts</h2>
                    <p class="ys-admin-subtle">Fast-moving variants that should be reviewed soon.</p>
                </div>
                <a href="{{ route('admin.inventory.index', ['tab' => 'inventory', 'status' => 'low']) }}" class="ys-admin-button-secondary">Review stock</a>
            </div>

            <div class="ys-admin-feed-list">
                @forelse ($inventory_alerts as $item)
                    <div class="ys-admin-feed-item is-alert">
                        <div class="ys-admin-feed-item-top">
                            <div>
                                <p class="ys-admin-feed-title">{{ $item['title'] }}</p>
                                <p class="ys-admin-feed-copy">{{ $item['sku'] }} / {{ $item['variant'] }}</p>
                            </div>

                            <div class="text-right">
                                <p class="ys-admin-feed-amount">On hand: {{ $item['on_hand'] }}</p>
                                <x-admin.status-pill :tone="$item['tone']">{{ $item['status'] }}</x-admin.status-pill>
                            </div>
                        </div>

                        <div class="ys-admin-feed-item-bottom">
                            <span>{{ $item['note'] }}</span>
                            <span>Reorder at {{ $item['reorder_level'] }}</span>
                        </div>
                    </div>
                @empty
                    <div class="ys-admin-empty-state is-compact">
                        <span class="ys-admin-empty-state-icon">
                            <x-admin.icon name="alert" class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="ys-admin-empty-state-title">Stock looks healthy right now.</p>
                            <p class="ys-admin-empty-state-copy">Low-stock alerts will appear here once any SKU falls below its reorder threshold.</p>
                        </div>
                    </div>
                @endforelse
            </div>
        </article>
    </section>

    <section class="ys-admin-panel" data-admin-panel>
        <div class="ys-admin-panel-heading">
            <div>
                <h2 class="ys-admin-panel-title">Live Activity</h2>
                <p class="ys-admin-subtle">Sales and inventory alerts refresh automatically without leaving the dashboard.</p>
            </div>
            <div class="rounded-full border border-white/10 bg-white/[0.03] px-3 py-2 text-xs uppercase tracking-[0.24em] text-ys-ivory/48" data-admin-live-status>
                Live via polling
            </div>
        </div>

        <div class="ys-admin-live-list" data-admin-live-feed-list>
            @forelse ($live_activity as $activity)
                <div class="ys-admin-live-item">
                    <div class="ys-admin-live-item-main">
                        <span class="ys-admin-live-indicator is-{{ $activity['type'] }}"></span>
                        <div>
                            <p class="ys-admin-live-title">{{ $activity['title'] }}</p>
                            <p class="ys-admin-live-copy">{{ $activity['message'] }}</p>
                        </div>
                    </div>
                    <span class="ys-admin-live-time">{{ $activity['timestamp'] }}</span>
                </div>
            @empty
                <div class="ys-admin-empty-state is-compact">
                    <span class="ys-admin-empty-state-icon">
                        <x-admin.icon name="dashboard" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="ys-admin-empty-state-title">Activity stream is on standby.</p>
                        <p class="ys-admin-empty-state-copy">New orders, counter sales, and stock updates will appear here in near real time.</p>
                    </div>
                </div>
            @endforelse
        </div>
    </section>
@endsection
