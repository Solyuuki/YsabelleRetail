@php
    $variantDefaults = old('variants', $product->variants->map(function ($variant) {
        return [
            'id' => $variant->id,
            'name' => $variant->name,
            'sku' => $variant->sku,
            'barcode' => $variant->barcode,
            'size' => $variant->option_values['size'] ?? null,
            'color' => $variant->option_values['color'] ?? null,
            'price' => $variant->price,
            'compare_at_price' => $variant->compare_at_price,
            'cost_price' => $variant->cost_price,
            'supplier_name' => $variant->supplier_name,
            'weight_grams' => $variant->weight_grams,
            'status' => $variant->status,
            'quantity_on_hand' => $variant->inventoryItem?->quantity_on_hand ?? 0,
            'reorder_level' => $variant->inventoryItem?->reorder_level ?? 0,
            'allow_backorder' => $variant->inventoryItem?->allow_backorder ?? false,
        ];
    })->all());

    if ($variantDefaults === []) {
        $variantDefaults = [[
            'id' => null,
            'name' => 'Default Variant',
            'sku' => '',
            'barcode' => '',
            'size' => '',
            'color' => '',
            'price' => '',
            'compare_at_price' => '',
            'cost_price' => '',
            'supplier_name' => '',
            'weight_grams' => '',
            'status' => 'active',
            'quantity_on_hand' => 0,
            'reorder_level' => 4,
            'allow_backorder' => false,
        ]];
    }
@endphp

@if ($errors->any())
    <div class="ys-admin-form-error">
        <p class="font-semibold">Please review the form before saving.</p>
        <ul class="mt-2 space-y-1 text-sm">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $action }}" class="space-y-6" data-admin-form>
    @csrf
    @isset($method)
        @method($method)
    @endisset

    <section class="ys-admin-panel" data-admin-panel>
        <div class="ys-admin-panel-heading">
            <div>
                <h2 class="ys-admin-panel-title">Product Details</h2>
                <p class="ys-admin-subtle">Keep product metadata, merchandising state, and storefront presentation consistent.</p>
            </div>
        </div>

        <div class="ys-admin-grid-fields mt-5">
            <label class="ys-admin-field">
                <span class="ys-admin-label">Category</span>
                <select name="category_id" class="ys-admin-select">
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="ys-admin-field">
                <span class="ys-admin-label">Name</span>
                <input type="text" name="name" value="{{ old('name', $product->name) }}" class="ys-admin-input">
            </label>

            <label class="ys-admin-field">
                <span class="ys-admin-label">Slug</span>
                <input type="text" name="slug" value="{{ old('slug', $product->slug) }}" class="ys-admin-input">
            </label>

            <label class="ys-admin-field">
                <span class="ys-admin-label">Style Code</span>
                <input type="text" name="style_code" value="{{ old('style_code', $product->style_code) }}" class="ys-admin-input">
            </label>

            <label class="ys-admin-field">
                <span class="ys-admin-label">Status</span>
                <select name="status" class="ys-admin-select">
                    @foreach (['draft', 'active', 'archived'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $product->status) === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </label>

            <label class="ys-admin-field">
                <span class="ys-admin-label">Featured Rank</span>
                <input type="number" min="1" name="featured_rank" value="{{ old('featured_rank', $product->featured_rank) }}" class="ys-admin-input">
            </label>
        </div>

        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <label class="ys-admin-field">
                <span class="ys-admin-label">Primary Image URL</span>
                <input type="url" name="primary_image_url" value="{{ old('primary_image_url', $product->primary_image_url) }}" class="ys-admin-input">
            </label>

            <label class="ys-admin-field">
                <span class="ys-admin-label">Image Alt</span>
                <input type="text" name="image_alt" value="{{ old('image_alt', $product->image_alt) }}" class="ys-admin-input">
            </label>
        </div>

        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <label class="ys-admin-field">
                <span class="ys-admin-label">Short Description</span>
                <textarea name="short_description" class="ys-admin-textarea">{{ old('short_description', $product->short_description) }}</textarea>
            </label>

            <label class="ys-admin-field">
                <span class="ys-admin-label">Description</span>
                <textarea name="description" class="ys-admin-textarea">{{ old('description', $product->description) }}</textarea>
            </label>
        </div>

        <div class="mt-4 flex flex-wrap gap-6">
            <label class="inline-flex items-center gap-3 text-sm text-ys-ivory/70">
                <input type="hidden" name="is_featured" value="0">
                <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured))>
                Featured product
            </label>
            <label class="inline-flex items-center gap-3 text-sm text-ys-ivory/70">
                <input type="hidden" name="track_inventory" value="0">
                <input type="checkbox" name="track_inventory" value="1" @checked(old('track_inventory', $product->track_inventory ?? true))>
                Track inventory
            </label>
        </div>
    </section>

    <section class="ys-admin-panel" data-admin-panel>
        <div class="ys-admin-panel-heading">
            <div>
                <h2 class="ys-admin-panel-title">Variants and Stock</h2>
                <p class="ys-admin-subtle">Each variant keeps its own SKU, pricing, supplier, and stock controls. Quantity changes made here are recorded as audited stock movements.</p>
            </div>
            <button type="button" class="ys-admin-button-secondary" data-variant-add data-variant-target="#variant-list" data-variant-template="#variant-template">Add variant</button>
        </div>

        <div id="variant-list" class="mt-5 space-y-4">
            @foreach ($variantDefaults as $index => $variant)
                @include('admin.catalog.products._variant-row', ['index' => $index, 'variant' => $variant])
            @endforeach
        </div>
    </section>

    <div class="ys-admin-inline-actions">
        <button type="submit" class="ys-admin-button-primary" data-loading-label="Saving product...">{{ $submitLabel }}</button>
        <a href="{{ route('admin.catalog.products.index') }}" class="ys-admin-button-secondary">Back to products</a>
    </div>
</form>

<template id="variant-template">
    @include('admin.catalog.products._variant-row', ['index' => '__INDEX__', 'variant' => [
        'id' => null,
        'name' => '',
        'sku' => '',
        'barcode' => '',
        'size' => '',
        'color' => '',
        'price' => '',
        'compare_at_price' => '',
        'cost_price' => '',
        'supplier_name' => '',
        'weight_grams' => '',
        'status' => 'active',
        'quantity_on_hand' => 0,
        'reorder_level' => 0,
        'allow_backorder' => false,
    ]])
</template>
