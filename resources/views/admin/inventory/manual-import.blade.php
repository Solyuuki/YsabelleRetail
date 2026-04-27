@extends('layouts.admin', ['title' => 'Manual Stock Update | Ysabelle Retail'])

@section('content')
    <x-admin.page-header
        eyebrow="Inventory"
        :title="str($movementType)->headline()->toString()"
        description="Record manual stock changes with reference numbers, supplier context, and permanent movement history."
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

    <form method="POST" action="{{ route('admin.inventory.manual-import.store') }}" class="space-y-6" data-admin-form>
        @csrf
        <section class="ys-admin-panel" data-admin-panel>
            <div class="ys-admin-grid-fields">
                <label class="ys-admin-field">
                    <span class="ys-admin-label">Movement Type</span>
                    <select name="type" class="ys-admin-select">
                        @foreach ($movementTypes as $type)
                            <option value="{{ $type }}" @selected(old('type', $movementType) === $type)>{{ str($type)->headline() }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="ys-admin-field">
                    <span class="ys-admin-label">Variant</span>
                    <select name="product_variant_id" class="ys-admin-select">
                        @foreach ($variants as $variant)
                            <option value="{{ $variant->id }}" @selected(old('product_variant_id', request('variant')) == $variant->id)>{{ $variant->sku }} · {{ $variant->product->name }} · {{ $variant->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="ys-admin-field">
                    <span class="ys-admin-label">Quantity</span>
                    <input type="number" name="quantity" value="{{ old('quantity', 1) }}" class="ys-admin-input">
                </label>

                <label class="ys-admin-field">
                    <span class="ys-admin-label">Cost Price</span>
                    <input type="number" step="0.01" min="0" name="cost_price" value="{{ old('cost_price') }}" class="ys-admin-input">
                </label>

                <label class="ys-admin-field">
                    <span class="ys-admin-label">Supplier</span>
                    <input type="text" name="supplier_name" value="{{ old('supplier_name') }}" class="ys-admin-input">
                </label>

                <label class="ys-admin-field">
                    <span class="ys-admin-label">Reference Number</span>
                    <input type="text" name="reference_number" value="{{ old('reference_number') }}" class="ys-admin-input">
                </label>
            </div>

            <label class="ys-admin-field mt-4">
                <span class="ys-admin-label">Notes</span>
                <textarea name="notes" class="ys-admin-textarea">{{ old('notes') }}</textarea>
            </label>
        </section>

        <div class="ys-admin-inline-actions">
            <button type="submit" class="ys-admin-button-primary" data-loading-label="Recording movement...">Record movement</button>
            <a href="{{ route('admin.inventory.index') }}" class="ys-admin-button-secondary">Back to inventory</a>
        </div>
    </form>
@endsection
