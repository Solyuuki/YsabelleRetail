@extends('layouts.admin', ['title' => 'Inventory | Ysabelle Retail'])

@section('content')
    @php
        $tabs = [
            'inventory' => ['label' => 'Inventory', 'route' => route('admin.inventory.index', ['tab' => 'inventory'])],
            'add-stock' => ['label' => 'Add Stock', 'route' => route('admin.inventory.index', ['tab' => 'add-stock'])],
            'batch-import' => ['label' => 'Batch Import', 'route' => route('admin.inventory.index', ['tab' => 'batch-import'])],
            'movements' => ['label' => 'Movements', 'route' => route('admin.inventory.index', ['tab' => 'movements'])],
        ];
    @endphp

    <x-admin.page-header
        eyebrow="Stock"
        title="Stock management"
        description="Inventory, manual updates, imports, and movement history in one place."
    >
        <a href="{{ route('admin.inventory.index', ['tab' => 'add-stock', 'type' => 'stock_in']) }}" class="ys-admin-button-primary">Add stock</a>
        <a href="{{ route('admin.inventory.index', ['tab' => 'batch-import']) }}" class="ys-admin-button-secondary">Batch import</a>
        <a href="{{ route('admin.pos.create') }}" class="ys-admin-button-secondary">Walk-in POS</a>
    </x-admin.page-header>

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
            ['label' => 'Active Variants', 'value' => number_format($inventoryOverview['active_variants']), 'meta' => 'Ready to sell'],
            ['label' => 'Units on Hand', 'value' => number_format($inventoryOverview['units_on_hand']), 'meta' => 'Current stock'],
            ['label' => 'Low Stock', 'value' => number_format($inventoryOverview['low_stock_items']), 'meta' => 'Needs replenishment'],
            ['label' => 'Out of Stock', 'value' => number_format($inventoryOverview['out_of_stock_items']), 'meta' => 'Urgent'],
            ['label' => 'Import Batches', 'value' => number_format($inventoryOverview['batch_imports']), 'meta' => 'Completed uploads'],
        ] as $card)
            <article class="ys-admin-stat-card" data-admin-panel>
                <p class="ys-admin-stat-label">{{ $card['label'] }}</p>
                <p class="ys-admin-stat-value">{{ $card['value'] }}</p>
                <p class="ys-admin-stat-meta">{{ $card['meta'] }}</p>
            </article>
        @endforeach
    </section>

    <section class="ys-admin-panel" data-admin-panel>
        <div class="ys-admin-tab-nav">
            @foreach ($tabs as $key => $tab)
                <a href="{{ $tab['route'] }}" class="ys-admin-tab-link {{ $activeTab === $key ? 'is-active' : '' }}">
                    {{ $tab['label'] }}
                </a>
            @endforeach
        </div>

        @if ($activeTab === 'inventory')
            <div class="ys-admin-panel-heading mt-6">
                <div>
                    <h2 class="ys-admin-panel-title">Inventory</h2>
                    <p class="ys-admin-subtle">Use one table for stock review and quick stock actions.</p>
                </div>
            </div>

            <form method="GET" class="ys-admin-toolbar mt-5">
                <input type="hidden" name="tab" value="inventory">
                <input type="text" name="search" value="{{ $filters['search'] }}" class="ys-admin-input" placeholder="Search SKU, variant, or product">
                <select name="status" class="ys-admin-select">
                    @foreach (['all' => 'All stock', 'active' => 'Active variants', 'archived' => 'Archived variants', 'low' => 'Low stock', 'out' => 'Out of stock'] as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="ys-admin-button-secondary">Apply</button>
            </form>

            <div class="ys-admin-table-wrap mt-5">
                <table class="ys-admin-table">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Product</th>
                            <th>On Hand</th>
                            <th>Available</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($variants as $variant)
                            @php
                                $inventory = $variant->inventoryItem;
                                $tone = ($inventory?->quantity_on_hand ?? 0) <= 0 ? 'danger' : (($inventory?->quantity_on_hand ?? 0) <= ($inventory?->reorder_level ?? 0) ? 'warning' : 'success');
                            @endphp
                            <tr>
                                <td>
                                    <p class="font-semibold text-ys-ivory">{{ $variant->sku }}</p>
                                    <p class="text-xs text-ys-ivory/38">{{ $variant->name }}</p>
                                </td>
                                <td>
                                    <p class="font-semibold text-ys-ivory">{{ $variant->product->name }}</p>
                                    <p class="text-xs text-ys-ivory/38">{{ $variant->product->category?->name ?? 'Uncategorized' }}</p>
                                </td>
                                <td>{{ $inventory?->quantity_on_hand ?? 0 }}</td>
                                <td>
                                    <p>{{ $inventory?->available_quantity ?? 0 }}</p>
                                    <p class="text-xs text-ys-ivory/38">Reorder at {{ $inventory?->reorder_level ?? 0 }}</p>
                                </td>
                                <td>
                                    <x-admin.status-pill :tone="$tone">
                                        {{ ($inventory?->quantity_on_hand ?? 0) <= 0 ? 'Out' : (($inventory?->quantity_on_hand ?? 0) <= ($inventory?->reorder_level ?? 0) ? 'Low' : 'Healthy') }}
                                    </x-admin.status-pill>
                                </td>
                                <td class="text-right">
                                    <a href="{{ route('admin.inventory.index', ['tab' => 'add-stock', 'variant' => $variant->id, 'type' => 'stock_in']) }}" class="ys-admin-button-secondary">Update stock</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="ys-admin-empty-panel">No inventory records matched the current filters.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-5">
                {{ $variants->links('vendor.pagination.admin') }}
            </div>
        @endif

        @if ($activeTab === 'add-stock')
            <div class="ys-admin-panel-heading mt-6">
                <div>
                    <h2 class="ys-admin-panel-title">Add Stock Manually</h2>
                    <p class="ys-admin-subtle">Use this for direct stock in, stock out, or adjustment entries.</p>
                </div>
            </div>

            <div class="grid gap-6 pt-5 xl:grid-cols-[1.25fr_0.75fr]">
                <form method="POST" action="{{ route('admin.inventory.manual-import.store') }}" class="space-y-6" data-admin-form>
                    @csrf
                    <section class="ys-admin-panel is-nested">
                        <div class="ys-admin-grid-fields">
                            <label class="ys-admin-field">
                                <span class="ys-admin-label">Change Type</span>
                                <select name="type" class="ys-admin-select">
                                    @foreach ($movementTypes as $type)
                                        <option value="{{ $type }}" @selected(old('type', $movementType) === $type)>{{ str($type)->headline() }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="ys-admin-field">
                                <span class="ys-admin-label">Variant</span>
                                <select name="product_variant_id" class="ys-admin-select">
                                    @foreach ($variantOptions as $variant)
                                        <option value="{{ $variant->id }}" @selected(old('product_variant_id', $selectedVariantId) == $variant->id)>{{ $variant->sku }} / {{ $variant->product->name }} / {{ $variant->name }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="ys-admin-field">
                                <span class="ys-admin-label">Quantity</span>
                                <input type="number" name="quantity" value="{{ old('quantity', 1) }}" min="1" class="ys-admin-input">
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
                                <span class="ys-admin-label">Reference</span>
                                <input type="text" name="reference_number" value="{{ old('reference_number') }}" class="ys-admin-input">
                            </label>
                        </div>

                        <label class="ys-admin-field mt-4">
                            <span class="ys-admin-label">Notes</span>
                            <textarea name="notes" class="ys-admin-textarea">{{ old('notes') }}</textarea>
                        </label>

                        <div class="mt-5 ys-admin-inline-actions">
                            <button type="submit" class="ys-admin-button-primary" data-loading-label="Recording movement...">Save movement</button>
                            <a href="{{ route('admin.inventory.index', ['tab' => 'inventory']) }}" class="ys-admin-button-secondary">Back to inventory</a>
                        </div>
                    </section>
                </form>

                <aside class="space-y-4">
                    <div class="ys-admin-detail-panel">
                        <p class="ys-admin-label">Quick Guide</p>
                        <ul class="ys-admin-helper-list mt-3">
                            <li>`Stock In` for replenishment</li>
                            <li>`Stock Out` for damaged or removed units</li>
                            <li>`Adjustment` for recount corrections</li>
                        </ul>
                    </div>

                    <details class="ys-admin-detail-panel">
                        <summary class="ys-admin-detail-summary">What gets recorded</summary>
                        <p class="ys-admin-subtle mt-3">Each change stores the user, timestamp, quantity delta, reference, and notes in the stock movement log.</p>
                    </details>
                </aside>
            </div>
        @endif

        @if ($activeTab === 'batch-import')
            <div class="ys-admin-panel-heading mt-6">
                <div>
                    <h2 class="ys-admin-panel-title">Batch Import</h2>
                    <p class="ys-admin-subtle">Upload a file, review the preview, then commit the stock update.</p>
                </div>
                <a href="{{ route('admin.inventory.batch-imports.template') }}" class="ys-admin-button-secondary">Download template</a>
            </div>

            <div class="grid gap-6 pt-5 xl:grid-cols-[1.1fr_0.9fr]">
                <form method="POST" action="{{ route('admin.inventory.batch-imports.preview') }}" enctype="multipart/form-data" class="space-y-6" data-admin-form>
                    @csrf
                    <section class="ys-admin-panel is-nested">
                        <label class="ys-admin-field">
                            <span class="ys-admin-label">CSV or Excel File</span>
                            <input type="file" name="file" class="ys-admin-input" accept=".csv,.xlsx,.xls">
                        </label>

                        <details class="ys-admin-detail-panel mt-4">
                            <summary class="ys-admin-detail-summary">Required columns</summary>
                            <p class="ys-admin-subtle mt-3">`sku`, `product_name`, `variant`, `quantity`, `cost_price`, `supplier`, `notes`</p>
                        </details>

                        <div class="mt-5 ys-admin-inline-actions">
                            <button type="submit" class="ys-admin-button-primary" data-loading-label="Parsing file...">Preview import</button>
                        </div>
                    </section>
                </form>

                <section class="ys-admin-panel is-nested">
                    <div class="ys-admin-panel-heading">
                        <div>
                            <h3 class="ys-admin-panel-title">Import History</h3>
                            <p class="ys-admin-subtle">Recent stock upload batches.</p>
                        </div>
                    </div>

                    <div class="ys-admin-table-wrap mt-5">
                        <table class="ys-admin-table">
                            <thead>
                                <tr>
                                    <th>Batch</th>
                                    <th>Rows</th>
                                    <th>Status</th>
                                    <th>Uploaded</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($importBatches as $batch)
                                    <tr>
                                        <td>
                                            <p class="font-semibold text-ys-ivory">{{ $batch->reference_number }}</p>
                                            <p class="text-xs text-ys-ivory/38">{{ $batch->original_filename }}</p>
                                        </td>
                                        <td>
                                            <p>{{ $batch->imported_rows }}/{{ $batch->total_rows }}</p>
                                            <p class="text-xs text-ys-ivory/38">{{ $batch->stock_movements_count }} movement(s)</p>
                                        </td>
                                        <td>
                                            <x-admin.status-pill :tone="$batch->status === 'completed' ? 'success' : 'warning'">
                                                {{ str($batch->status)->headline() }}
                                            </x-admin.status-pill>
                                        </td>
                                        <td>
                                            <p>{{ $batch->uploadedBy?->name ?? 'Admin' }}</p>
                                            <p class="text-xs text-ys-ivory/38">{{ $batch->created_at?->format('M d, Y h:i A') }}</p>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4">
                                            <div class="ys-admin-empty-panel">No import history yet.</div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-5">
                        {{ $importBatches->links('vendor.pagination.admin') }}
                    </div>
                </section>
            </div>

            @if ($preview)
                <section class="ys-admin-panel is-nested mt-6">
                    <div class="ys-admin-panel-heading">
                        <div>
                            <h3 class="ys-admin-panel-title">Preview</h3>
                            <p class="ys-admin-subtle">{{ $preview['filename'] }}</p>
                        </div>
                        <div class="ys-admin-inline-actions text-sm text-ys-ivory/55">
                            <span>{{ $preview['summary']['total_rows'] }} total</span>
                            <span>{{ $preview['summary']['valid_rows'] }} valid</span>
                            <span>{{ $preview['summary']['invalid_rows'] }} invalid</span>
                        </div>
                    </div>

                    <div class="ys-admin-table-wrap mt-5">
                        <table class="ys-admin-table">
                            <thead>
                                <tr>
                                    <th>Row</th>
                                    <th>SKU</th>
                                    <th>Quantity</th>
                                    <th>Resolved Variant</th>
                                    <th>Validation</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($preview['rows'] as $row)
                                    <tr>
                                        <td>{{ $row['row_number'] }}</td>
                                        <td>{{ $row['values']['sku'] }}</td>
                                        <td>{{ $row['values']['quantity'] }}</td>
                                        <td>{{ $row['product_name'] ? "{$row['product_name']} / {$row['variant_name']}" : 'Not found' }}</td>
                                        <td>
                                            @if ($row['errors'] === [])
                                                <x-admin.status-pill tone="success">Valid</x-admin.status-pill>
                                            @else
                                                <div class="space-y-1 text-sm text-[#ffcece]">
                                                    @foreach ($row['errors'] as $error)
                                                        <p>{{ $error }}</p>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <form method="POST" action="{{ route('admin.inventory.batch-imports.store') }}" class="mt-5" data-admin-form>
                        @csrf
                        <input type="hidden" name="preview_token" value="{{ $preview['token'] }}">
                        <button type="submit" class="ys-admin-button-primary" data-loading-label="Importing stock..." @disabled($preview['summary']['invalid_rows'] > 0)>Commit import</button>
                    </form>
                </section>
            @endif
        @endif

        @if ($activeTab === 'movements')
            <div class="ys-admin-panel-heading mt-6">
                <div>
                    <h2 class="ys-admin-panel-title">Stock Movement History</h2>
                    <p class="ys-admin-subtle">Every stock change is recorded with its source, reference, and user.</p>
                </div>
            </div>

            <div class="ys-admin-table-wrap mt-5">
                <table class="ys-admin-table">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Variant</th>
                            <th>Type</th>
                            <th>Change</th>
                            <th>Source</th>
                            <th>Reference</th>
                            <th>Admin</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($stockMovements as $movement)
                            <tr>
                                <td>{{ optional($movement->occurred_at)->format('M d, Y h:i A') }}</td>
                                <td>
                                    <p class="font-semibold text-ys-ivory">{{ $movement->variant?->product?->name }}</p>
                                    <p class="text-xs text-ys-ivory/38">{{ $movement->variant?->sku }}</p>
                                </td>
                                <td>{{ str($movement->type)->headline() }}</td>
                                <td class="{{ $movement->quantity_delta < 0 ? 'text-[#ffb1b1]' : 'text-[#9ae0b3]' }}">
                                    {{ $movement->quantity_delta > 0 ? '+' : '' }}{{ $movement->quantity_delta }}
                                </td>
                                <td>
                                    @if ($movement->order)
                                        {{ str($movement->order->source)->headline() }}
                                    @elseif ($movement->importBatch)
                                        Batch Import
                                    @else
                                        Manual
                                    @endif
                                </td>
                                <td>{{ $movement->reference_number ?: '-' }}</td>
                                <td>{{ $movement->actor?->name ?? 'System' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="ys-admin-empty-panel">No stock movements recorded yet.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-5">
                {{ $stockMovements->links('vendor.pagination.admin') }}
            </div>
        @endif
    </section>
@endsection
