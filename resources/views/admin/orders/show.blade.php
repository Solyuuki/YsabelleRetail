@extends('layouts.admin', ['title' => 'Order Details | Ysabelle Retail'])

@section('content')
    <x-admin.page-header
        eyebrow="Receipt"
        :title="$order->order_number"
        description="Printable summary with line items, payment state, and linked stock movements."
    >
        <button type="button" class="ys-admin-button-secondary" data-print-page>Print receipt</button>
    </x-admin.page-header>

    <section class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <article class="ys-admin-panel" data-admin-panel>
            <div class="ys-admin-panel-heading">
                <div>
                    <h2 class="ys-admin-panel-title">Line Items</h2>
                    <p class="ys-admin-subtle">{{ str($order->source)->headline() }} transaction / {{ optional($order->placed_at)->format('M d, Y h:i A') }}</p>
                </div>
            </div>

            <div class="ys-admin-table-wrap mt-5">
                <table class="ys-admin-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Qty</th>
                            <th>Unit Price</th>
                            <th>Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order->items as $item)
                            <tr>
                                <td>
                                    <p class="font-semibold text-ys-ivory">{{ $item->product_name }}</p>
                                    <p class="text-xs text-ys-ivory/38">{{ $item->variant_name }}</p>
                                </td>
                                <td>{{ $item->sku }}</td>
                                <td>{{ $item->quantity }}</td>
                                <td>PHP {{ number_format((float) $item->unit_price, 2) }}</td>
                                <td>PHP {{ number_format((float) $item->line_total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </article>

        <div class="space-y-6">
            <article class="ys-admin-panel" data-admin-panel>
                <div class="space-y-3 text-sm text-ys-ivory/68">
                    <div class="flex items-center justify-between">
                        <span>Customer</span>
                        <span class="font-semibold text-ys-ivory">{{ $order->customer_name ?: 'Registered customer' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Payment</span>
                        <span class="font-semibold text-ys-ivory">{{ strtoupper((string) $order->payment_method) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Status</span>
                        <x-admin.status-pill :tone="$order->status === 'completed' ? 'success' : 'warning'">{{ $order->status }}</x-admin.status-pill>
                    </div>
                    <div class="flex items-center justify-between border-t border-white/7 pt-3">
                        <span>Total</span>
                        <span class="text-lg font-semibold text-ys-gold">PHP {{ number_format((float) $order->grand_total, 2) }}</span>
                    </div>
                </div>
            </article>

            <article class="ys-admin-panel" data-admin-panel>
                <div class="ys-admin-panel-heading">
                    <div>
                        <h2 class="ys-admin-panel-title">Inventory Audit</h2>
                        <p class="ys-admin-subtle">Movement entries linked to this transaction.</p>
                    </div>
                </div>
                <div class="space-y-3 pt-4">
                    @forelse ($order->stockMovements as $movement)
                        <div class="rounded-[1rem] border border-white/7 bg-white/[0.03] px-4 py-3">
                            <p class="text-sm font-semibold text-ys-ivory">{{ $movement->variant?->sku }}</p>
                            <p class="text-xs text-ys-ivory/40">{{ str($movement->type)->headline() }} / {{ $movement->quantity_delta }}</p>
                        </div>
                    @empty
                        <div class="ys-admin-empty-panel">No linked stock movements were found for this order.</div>
                    @endforelse
                </div>
            </article>
        </div>
    </section>
@endsection
