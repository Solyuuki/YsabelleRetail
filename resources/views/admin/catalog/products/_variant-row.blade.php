@php
    $variantStatus = $variant['status'] ?? 'active';
    $variantSize = trim((string) ($variant['size'] ?? ''));
    $variantColor = trim((string) ($variant['color'] ?? ''));
    $variantSku = trim((string) ($variant['sku'] ?? ''));
@endphp

<article class="ys-admin-variant-card" data-variant-row data-variant-existing="{{ filled($variant['id']) ? '1' : '0' }}">
    <input type="hidden" name="variants[{{ $index }}][id]" value="{{ $variant['id'] }}" data-variant-id>

    <div class="ys-admin-variant-card-head">
        <div>
            <p class="ys-admin-detail-kicker">Variant</p>
            <h3 class="ys-admin-variant-card-title" data-variant-title>
                {{ $variantSize !== '' || $variantColor !== '' ? trim(implode(' / ', array_filter([$variantSize, $variantColor]))) : 'New size or color option' }}
            </h3>
            <p class="ys-admin-variant-card-copy" data-variant-summary>
                {{ $variantSku !== '' ? 'SKU '.$variantSku : 'Add size, color, and SKU so your team can identify this stock option quickly.' }}
            </p>
        </div>

        <div class="ys-admin-inline-actions">
            <button type="button" class="ys-admin-button-secondary" data-variant-duplicate>Duplicate Variant</button>
            <button type="button" class="ys-admin-link-danger" data-variant-remove>Remove Variant</button>
        </div>
    </div>

    @if (filled($variant['id']))
        <p class="mt-4 text-xs uppercase tracking-[0.18em] text-ys-ivory/34">
            Removing this saved variant keeps its stock history safe. Variants with inventory history are archived instead of hard-deleted.
        </p>
    @endif

    <div class="mt-5 grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
        <label class="ys-admin-field">
            <span class="ys-admin-label">Size</span>
            <input
                type="text"
                name="variants[{{ $index }}][size]"
                value="{{ $variant['size'] }}"
                class="ys-admin-input"
                placeholder="Example: 38"
                data-variant-size
            >
        </label>

        <label class="ys-admin-field">
            <span class="ys-admin-label">Color</span>
            <input
                type="text"
                name="variants[{{ $index }}][color]"
                value="{{ $variant['color'] }}"
                class="ys-admin-input"
                placeholder="Example: Black / Gold"
                data-variant-color
            >
        </label>

        <label class="ys-admin-field">
            <span class="ys-admin-label">SKU</span>
            <input
                type="text"
                name="variants[{{ $index }}][sku]"
                value="{{ $variant['sku'] }}"
                class="ys-admin-input"
                placeholder="Example: YSV-AURUM-38"
                data-variant-sku
            >
        </label>

        <label class="ys-admin-field">
            <span class="ys-admin-label">Selling Price</span>
            <input
                type="number"
                step="0.01"
                min="0"
                name="variants[{{ $index }}][price]"
                value="{{ $variant['price'] }}"
                class="ys-admin-input"
                placeholder="0.00"
                data-variant-price
            >
        </label>

        <label class="ys-admin-field">
            <span class="ys-admin-label">Original Price</span>
            <input
                type="number"
                step="0.01"
                min="0"
                name="variants[{{ $index }}][compare_at_price]"
                value="{{ $variant['compare_at_price'] }}"
                class="ys-admin-input"
                placeholder="0.00"
                data-variant-compare-price
            >
            <span class="ys-admin-field-help">If Original Price is higher than Selling Price, the SALE badge appears automatically.</span>
        </label>

        <label class="ys-admin-field">
            <span class="ys-admin-label">Status</span>
            <select name="variants[{{ $index }}][status]" class="ys-admin-select" data-variant-status>
                @foreach (['active' => 'Active', 'archived' => 'Archived'] as $statusValue => $statusLabel)
                    <option value="{{ $statusValue }}" @selected($variantStatus === $statusValue)>{{ $statusLabel }}</option>
                @endforeach
            </select>
        </label>

        <label class="ys-admin-field">
            <span class="ys-admin-label">Stock Quantity</span>
            <input
                type="number"
                step="1"
                min="0"
                name="variants[{{ $index }}][quantity_on_hand]"
                value="{{ $variant['quantity_on_hand'] }}"
                class="ys-admin-input"
                data-variant-quantity
            >
            <span class="ys-admin-field-help">Saving a new value records a stock movement instead of silently rewriting inventory.</span>
        </label>

        <label class="ys-admin-field">
            <span class="ys-admin-label">Low Stock Alert</span>
            <input
                type="number"
                step="1"
                min="0"
                name="variants[{{ $index }}][reorder_level]"
                value="{{ $variant['reorder_level'] }}"
                class="ys-admin-input"
                data-variant-reorder
            >
        </label>

        <label class="ys-admin-field ys-admin-field-checkbox">
            <span class="ys-admin-label">Backorder</span>
            <input type="hidden" name="variants[{{ $index }}][allow_backorder]" value="0">
            <span class="ys-admin-checkbox-card">
                <input type="checkbox" name="variants[{{ $index }}][allow_backorder]" value="1" @checked($variant['allow_backorder'] ?? false) data-variant-backorder>
                <span>
                    <strong>Allow selling when out of stock</strong>
                    <small>Customers can still buy this variant when quantity reaches zero.</small>
                </span>
            </span>
        </label>
    </div>

    <details class="ys-admin-variant-advanced">
        <summary class="ys-admin-advanced-inline-summary">
            <span>Advanced Variant Details</span>
            <span class="ys-admin-advanced-inline-note">Barcode, supplier, internal label, cost, and shipping weight</span>
        </summary>

        <div class="mt-4 grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
            <label class="ys-admin-field">
                <span class="ys-admin-label">Variant Label</span>
                <input type="text" name="variants[{{ $index }}][name]" value="{{ $variant['name'] }}" class="ys-admin-input" placeholder="Example: Size 38 / Black">
            </label>

            <label class="ys-admin-field">
                <span class="ys-admin-label">Barcode</span>
                <input type="text" name="variants[{{ $index }}][barcode]" value="{{ $variant['barcode'] }}" class="ys-admin-input">
            </label>

            <label class="ys-admin-field">
                <span class="ys-admin-label">Supplier</span>
                <input type="text" name="variants[{{ $index }}][supplier_name]" value="{{ $variant['supplier_name'] }}" class="ys-admin-input">
            </label>

            <label class="ys-admin-field">
                <span class="ys-admin-label">Cost Price</span>
                <input type="number" step="0.01" min="0" name="variants[{{ $index }}][cost_price]" value="{{ $variant['cost_price'] }}" class="ys-admin-input" placeholder="0.00">
            </label>

            <label class="ys-admin-field">
                <span class="ys-admin-label">Weight (g)</span>
                <input type="number" step="1" min="0" name="variants[{{ $index }}][weight_grams]" value="{{ $variant['weight_grams'] }}" class="ys-admin-input" placeholder="0">
            </label>
        </div>
    </details>
</article>
