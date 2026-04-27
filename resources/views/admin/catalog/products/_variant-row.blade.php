<article class="rounded-[1.2rem] border border-white/7 bg-white/[0.03] p-4" data-variant-row>
    <input type="hidden" name="variants[{{ $index }}][id]" value="{{ $variant['id'] }}">

    <div class="flex items-center justify-between gap-3">
        <h3 class="text-sm font-semibold text-ys-ivory">Variant {{ is_numeric($index) ? $index + 1 : '#'.$index }}</h3>
        <button type="button" class="ys-admin-link-danger" data-variant-remove>Remove</button>
    </div>

    <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            'name' => 'Variant Name',
            'sku' => 'SKU',
            'barcode' => 'Barcode',
            'supplier_name' => 'Supplier',
            'size' => 'Size',
            'color' => 'Color',
            'price' => 'Sell Price',
            'compare_at_price' => 'Compare Price',
            'cost_price' => 'Cost Price',
            'weight_grams' => 'Weight (g)',
            'quantity_on_hand' => 'Target On Hand',
            'reorder_level' => 'Reorder Level',
        ] as $field => $label)
            <label class="ys-admin-field">
                <span class="ys-admin-label">{{ $label }}</span>
                <input type="{{ in_array($field, ['price', 'compare_at_price', 'cost_price', 'weight_grams', 'quantity_on_hand', 'reorder_level'], true) ? 'number' : 'text' }}"
                    step="{{ in_array($field, ['price', 'compare_at_price', 'cost_price'], true) ? '0.01' : '1' }}"
                    name="variants[{{ $index }}][{{ $field }}]"
                    value="{{ $variant[$field] }}"
                    class="ys-admin-input">
                @if ($field === 'quantity_on_hand')
                    <span class="mt-2 text-xs text-ys-ivory/45">Saving a new value records a stock movement instead of silently rewriting inventory.</span>
                @endif
            </label>
        @endforeach

        <label class="ys-admin-field">
            <span class="ys-admin-label">Status</span>
            <select name="variants[{{ $index }}][status]" class="ys-admin-select">
                @foreach (['active', 'archived'] as $status)
                    <option value="{{ $status }}" @selected(($variant['status'] ?? 'active') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </label>

        <label class="ys-admin-field flex items-end gap-3">
            <input type="hidden" name="variants[{{ $index }}][allow_backorder]" value="0">
            <input type="checkbox" name="variants[{{ $index }}][allow_backorder]" value="1" @checked($variant['allow_backorder'] ?? false)>
            <span class="text-sm text-ys-ivory/68">Allow backorder</span>
        </label>
    </div>
</article>
