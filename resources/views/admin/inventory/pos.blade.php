@extends('layouts.admin', ['title' => 'Walk-in POS | Ysabelle Retail'])

@section('content')
    <x-admin.page-header
        eyebrow="POS"
        title="Walk-in sales"
        description="Fast in-store checkout with immediate payment and stock deduction."
    />

    @if ($errors->any())
        <div class="ys-admin-form-error">
            <ul class="space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="ys-admin-stat-grid">
        @foreach ([
            ['label' => 'Flow', 'value' => 'In-store', 'meta' => 'No delivery'],
            ['label' => 'Customer', 'value' => 'Optional', 'meta' => 'Defaults to Walk-in Customer'],
            ['label' => 'Inventory', 'value' => 'Instant', 'meta' => 'Deducted after sale'],
        ] as $card)
            <article class="ys-admin-stat-card" data-admin-panel>
                <p class="ys-admin-stat-label">{{ $card['label'] }}</p>
                <p class="ys-admin-stat-value">{{ $card['value'] }}</p>
                <p class="ys-admin-stat-meta">{{ $card['meta'] }}</p>
            </article>
        @endforeach
    </section>

    <form method="POST" action="{{ route('admin.pos.store') }}" class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]" data-admin-form data-admin-pos data-search-endpoint="{{ route('admin.pos.search') }}" data-old-lines='@json($oldLines ?? [])'>
        @csrf
        <section class="ys-admin-panel space-y-4" data-admin-panel>
            <div>
                <h2 class="ys-admin-panel-title">Search products</h2>
                <p class="ys-admin-subtle mt-2">Look up active products by name, variant, or SKU.</p>
            </div>

            <input type="text" class="ys-admin-input" placeholder="Search live inventory..." data-pos-search>
            <div class="space-y-3" data-pos-results></div>
        </section>

        <section class="space-y-6">
            <article class="ys-admin-panel" data-admin-panel>
                <div class="ys-admin-panel-heading">
                    <div>
                        <h2 class="ys-admin-panel-title">Receipt</h2>
                        <p class="ys-admin-subtle">Only items, quantity, and payment are required.</p>
                    </div>
                </div>

                <div class="mt-4 space-y-3" data-pos-cart></div>
                <input type="hidden" name="lines_json" value="{{ old('lines_json', '[]') }}">

                <div class="mt-5 flex items-center justify-between border-t border-white/7 pt-4">
                    <span class="text-sm text-ys-ivory/55">Total</span>
                    <span class="text-xl font-semibold text-ys-gold">PHP <span data-pos-total>0.00</span></span>
                </div>
            </article>

            <article class="ys-admin-panel" data-admin-panel>
                <div class="ys-admin-grid-fields">
                    <label class="ys-admin-field">
                        <span class="ys-admin-label">Payment Method</span>
                        <select name="payment_method" class="ys-admin-select">
                            @foreach (['cash', 'gcash', 'card', 'other'] as $method)
                                <option value="{{ $method }}" @selected(old('payment_method', 'cash') === $method)>{{ strtoupper($method) }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="ys-admin-field">
                        <span class="ys-admin-label">Payment Status</span>
                        <select name="payment_status" class="ys-admin-select">
                            @foreach (['paid', 'pending', 'unpaid'] as $status)
                                <option value="{{ $status }}" @selected(old('payment_status', 'paid') === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <details class="ys-admin-detail-panel mt-4">
                    <summary class="ys-admin-detail-summary">Optional details</summary>
                    <div class="mt-4 space-y-4">
                        <label class="ys-admin-field">
                            <span class="ys-admin-label">Customer Name</span>
                            <input type="text" name="customer_name" value="{{ old('customer_name') }}" class="ys-admin-input" placeholder="Walk-in Customer">
                        </label>

                        <label class="ys-admin-field">
                            <span class="ys-admin-label">Notes</span>
                            <textarea name="notes" class="ys-admin-textarea">{{ old('notes') }}</textarea>
                        </label>
                    </div>
                </details>

                <div class="mt-5 ys-admin-inline-actions">
                    <button type="submit" class="ys-admin-button-primary" data-loading-label="Completing sale...">Complete sale</button>
                </div>
            </article>
        </section>
    </form>
@endsection
