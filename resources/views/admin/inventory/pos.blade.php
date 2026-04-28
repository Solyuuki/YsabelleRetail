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

    <form
        method="POST"
        action="{{ route('admin.pos.store') }}"
        class="ys-admin-pos-layout"
        data-admin-form
        data-admin-pos
        data-search-endpoint="{{ route('admin.pos.search') }}"
        data-old-lines='@json($oldLines ?? [])'
    >
        @csrf

        <section class="ys-admin-panel ys-admin-pos-catalog" data-admin-panel>
            <div class="ys-admin-pos-search-row">
                <label class="ys-admin-pos-search-field">
                    <span class="sr-only">Search products</span>
                    <svg class="ys-admin-pos-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <circle cx="11" cy="11" r="6"></circle>
                        <path d="m16 16 4 4" stroke-linecap="round"></path>
                    </svg>
                    <input
                        type="text"
                        class="ys-admin-pos-search-input"
                        placeholder="Search products by name, SKU, or category..."
                        autocomplete="off"
                        data-pos-search
                    >
                </label>
            </div>

            <div class="ys-admin-pos-results-meta">
                <span data-pos-results-label>Showing live inventory</span>
                <span data-pos-results-summary>8 per page</span>
            </div>

            <div class="ys-admin-pos-results-grid" data-pos-results></div>
            <div class="ys-admin-pos-results-pagination" data-pos-pagination></div>
        </section>

        <aside class="ys-admin-panel ys-admin-pos-sidebar" data-admin-panel>
            <div class="ys-admin-pos-sidebar-inner">
                <div class="ys-admin-pos-sidebar-section">
                    <div class="ys-admin-pos-sidebar-header">
                        <div>
                            <h2 class="ys-admin-panel-title">Current sale</h2>
                            <p class="ys-admin-subtle">Build the receipt from live stock only.</p>
                        </div>
                    </div>

                    <div class="ys-admin-pos-cart-list" data-pos-cart></div>
                    <input type="hidden" name="lines_json" value="{{ old('lines_json', '[]') }}">
                </div>

                <div class="ys-admin-pos-sidebar-section">
                    <div class="ys-admin-pos-field-grid">
                        <label class="ys-admin-field">
                            <span class="ys-admin-label">Customer</span>
                            <input type="text" name="customer_name" value="{{ old('customer_name') }}" class="ys-admin-input" placeholder="Name">
                        </label>

                        <label class="ys-admin-field">
                            <span class="ys-admin-label">Phone</span>
                            <input type="text" name="customer_phone" value="{{ old('customer_phone') }}" class="ys-admin-input" placeholder="Phone">
                        </label>
                    </div>

                    <div class="ys-admin-pos-field-grid">
                        <label class="ys-admin-field">
                            <span class="ys-admin-label">Payment</span>
                            <select name="payment_method" class="ys-admin-select">
                                @foreach (['cash', 'gcash', 'card', 'other'] as $method)
                                    <option value="{{ $method }}" @selected(old('payment_method', 'cash') === $method)>{{ strtoupper($method) }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="ys-admin-field">
                            <span class="ys-admin-label">Discount PHP</span>
                            <input
                                type="number"
                                name="discount_amount"
                                value="{{ old('discount_amount', '0') }}"
                                min="0"
                                step="0.01"
                                class="ys-admin-input"
                                placeholder="0"
                                data-pos-discount
                            >
                        </label>
                    </div>

                    <div class="ys-admin-pos-field-grid is-single">
                        <label class="ys-admin-field">
                            <span class="ys-admin-label">Payment Status</span>
                            <select name="payment_status" class="ys-admin-select">
                                @foreach (['paid', 'pending', 'unpaid'] as $status)
                                    <option value="{{ $status }}" @selected(old('payment_status', 'paid') === $status)>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <label class="ys-admin-field">
                        <span class="ys-admin-label">Notes</span>
                        <textarea name="notes" class="ys-admin-textarea" placeholder="Optional notes">{{ old('notes') }}</textarea>
                    </label>
                </div>

                <div class="ys-admin-pos-sidebar-section ys-admin-pos-summary">
                    <div class="ys-admin-pos-summary-row">
                        <span>Subtotal</span>
                        <strong>PHP <span data-pos-subtotal>0.00</span></strong>
                    </div>

                    <div class="ys-admin-pos-summary-row">
                        <span>Discount</span>
                        <strong>- PHP <span data-pos-discount-total>0.00</span></strong>
                    </div>

                    <div class="ys-admin-pos-summary-total">
                        <span>Total</span>
                        <strong>PHP <span data-pos-total>0.00</span></strong>
                    </div>

                    <button
                        type="submit"
                        class="ys-admin-button-primary ys-admin-pos-submit"
                        data-pos-submit
                        data-loading-label="Completing sale..."
                    >
                        Cart is empty
                    </button>
                </div>
            </div>
        </aside>
    </form>
@endsection
