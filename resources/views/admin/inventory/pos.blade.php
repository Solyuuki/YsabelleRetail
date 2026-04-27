@extends('layouts.admin', ['title' => 'Walk-in POS | Ysabelle Retail'])

@section('content')
    <x-admin.page-header
        eyebrow="POS"
        title="Walk-in sales"
        description="Search live inventory, build a receipt, and deduct stock from the same source used by online orders."
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

    <form method="POST" action="{{ route('admin.pos.store') }}" class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]" data-admin-form data-admin-pos data-search-endpoint="{{ route('admin.pos.search') }}">
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
                        <p class="ys-admin-subtle">All quantities are validated against live stock before the sale completes.</p>
                    </div>
                </div>

                <div class="mt-4 space-y-3" data-pos-cart></div>
                <input type="hidden" name="lines_json" value="[]">

                <div class="mt-5 flex items-center justify-between border-t border-white/7 pt-4">
                    <span class="text-sm text-ys-ivory/55">Total</span>
                    <span class="text-xl font-semibold text-ys-gold">PHP <span data-pos-total>0.00</span></span>
                </div>
            </article>

            <article class="ys-admin-panel" data-admin-panel>
                <div class="ys-admin-grid-fields">
                    <label class="ys-admin-field">
                        <span class="ys-admin-label">Customer Name</span>
                        <input type="text" name="customer_name" value="{{ old('customer_name') }}" class="ys-admin-input">
                    </label>

                    <label class="ys-admin-field">
                        <span class="ys-admin-label">Customer Phone</span>
                        <input type="text" name="customer_phone" value="{{ old('customer_phone') }}" class="ys-admin-input">
                    </label>

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

                <label class="ys-admin-field mt-4">
                    <span class="ys-admin-label">Notes</span>
                    <textarea name="notes" class="ys-admin-textarea">{{ old('notes') }}</textarea>
                </label>

                <div class="mt-5 ys-admin-inline-actions">
                    <button type="submit" class="ys-admin-button-primary" data-loading-label="Completing sale...">Complete sale</button>
                </div>
            </article>
        </section>
    </form>
@endsection
