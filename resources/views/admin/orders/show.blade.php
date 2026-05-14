@extends('layouts.admin', ['title' => 'Order Details | Ysabelle Retail'])

@section('content')
    <div class="ys-admin-print-hidden">
        <x-admin.page-header
            eyebrow="Receipt"
            :title="$order->order_number"
            description="Printable summary with line items, payment state, and linked stock movements."
        >
            <button type="button" class="ys-admin-button-secondary" data-print-page>Print receipt</button>
        </x-admin.page-header>
    </div>

    @if ($errors->any())
        <div class="ys-admin-form-error ys-admin-print-hidden">
            <ul class="space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $statusTone = match ($order->status) {
            'completed' => 'success',
            'processing' => 'neutral',
            default => 'warning',
        };
        $paymentTone = match ($order->payment_status) {
            'paid' => 'success',
            'pending' => 'warning',
            default => 'danger',
        };
        $fulfillmentTone = $order->fulfillment_status === 'fulfilled' ? 'success' : 'warning';
    @endphp

    <section class="ys-admin-receipt-sheet" data-admin-receipt>
        <header class="ys-admin-receipt-print-head ys-admin-print-only">
            <div>
                <p class="ys-admin-receipt-kicker">Ysabelle Retail Shop</p>
                <h1 class="ys-admin-receipt-title">Order Receipt</h1>
            </div>
            <div class="ys-admin-receipt-meta">
                <div>
                    <span>Receipt No.</span>
                    <strong>{{ $order->order_number }}</strong>
                </div>
                <div>
                    <span>Date / Time</span>
                    <strong>{{ optional($order->placed_at)->format('M d, Y h:i A') }}</strong>
                </div>
                <div>
                    <span>Customer</span>
                    <strong>{{ $order->customer_name ?: 'Registered customer' }}</strong>
                </div>
                <div>
                    <span>Payment Method</span>
                    <strong>{{ strtoupper((string) $order->payment_method) }}</strong>
                </div>
            </div>
        </header>

        <div class="ys-admin-receipt-grid grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
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
                                        <div class="flex items-center gap-3">
                                            <x-storefront.product-media
                                                :image-url="$item->product_image_url"
                                                :alt="$item->product_image_alt"
                                                :title="$item->product_name"
                                                eyebrow="Order Item"
                                                class="ys-admin-receipt-media h-14 w-14 rounded-xl border border-white/6"
                                                fallback-class="p-2.5"
                                            />
                                            <div>
                                                <p class="font-semibold text-ys-ivory">{{ $item->product_name }}</p>
                                                <p class="text-xs text-ys-ivory/38">{{ $item->variant_name }}</p>
                                            </div>
                                        </div>
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
                            <span>Method</span>
                            <span class="font-semibold text-ys-ivory">{{ strtoupper((string) $order->payment_method) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span>Status</span>
                            <x-admin.status-pill :tone="$statusTone">{{ $order->status }}</x-admin.status-pill>
                        </div>
                        <div class="flex items-center justify-between">
                            <span>Payment Status</span>
                            <x-admin.status-pill :tone="$paymentTone">{{ $order->payment_status }}</x-admin.status-pill>
                        </div>
                        <div class="flex items-center justify-between">
                            <span>Fulfillment</span>
                            <x-admin.status-pill :tone="$fulfillmentTone">{{ $order->fulfillment_status }}</x-admin.status-pill>
                        </div>
                        <div class="flex items-center justify-between border-t border-white/7 pt-3">
                            <span>Total</span>
                            <span class="text-lg font-semibold text-ys-gold">PHP {{ number_format((float) $order->grand_total, 2) }}</span>
                        </div>
                    </div>
                </article>

                <article class="ys-admin-panel ys-admin-print-hidden" data-admin-panel>
                    <div class="ys-admin-panel-heading">
                        <div>
                            <h2 class="ys-admin-panel-title">Order Lifecycle</h2>
                            <p class="ys-admin-subtle">Move simulated online orders from paid processing to completed without bypassing payment integrity.</p>
                        </div>
                    </div>

                    @if ($order->status === 'completed')
                        <div class="ys-admin-empty-panel mt-4">
                            This order is already completed and locked from moving back to an earlier lifecycle stage.
                        </div>
                    @else
                        <form method="POST" action="{{ route('admin.orders.lifecycle.update', $order) }}" class="space-y-4 pt-4" data-admin-form>
                            @csrf
                            @method('PATCH')

                            <div class="ys-admin-grid-fields">
                                <label class="ys-admin-field">
                                    <span class="ys-admin-label">Order Status</span>
                                    <select name="status" class="ys-admin-select">
                                        @foreach (['pending' => 'Pending', 'processing' => 'Processing', 'completed' => 'Completed'] as $value => $label)
                                            <option value="{{ $value }}" @selected(old('status', $order->status) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>

                                <label class="ys-admin-field">
                                    <span class="ys-admin-label">Payment Status</span>
                                    <select name="payment_status" class="ys-admin-select">
                                        @foreach (['unpaid' => 'Unpaid', 'pending' => 'Pending', 'paid' => 'Paid'] as $value => $label)
                                            <option value="{{ $value }}" @selected(old('payment_status', $order->payment_status) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>

                            <p class="ys-admin-subtle">
                                Completed orders require a paid payment status. Paid orders stay paid and cannot move backward.
                            </p>

                            <div class="ys-admin-inline-actions">
                                <button type="submit" class="ys-admin-button-primary" data-loading-label="Updating order...">Update lifecycle</button>
                            </div>
                        </form>
                    @endif
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
        </div>
    </section>
@endsection
