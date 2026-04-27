@extends('layouts.admin', ['title' => 'Inventory | Ysabelle Retail'])

@section('content')
    <x-admin.page-header
        eyebrow="Inventory"
        title="Stock control"
        description="Monitor real-time stock, identify shortages, and audit all manual, online, and walk-in movements."
    >
        <a href="{{ route('admin.inventory.manual-import.create', ['type' => 'stock_in']) }}" class="ys-admin-button-primary">Stock in</a>
        <a href="{{ route('admin.inventory.manual-import.create', ['type' => 'adjustment']) }}" class="ys-admin-button-secondary">Adjustment</a>
    </x-admin.page-header>

    <section class="ys-admin-panel" data-admin-panel>
        <form method="GET" class="ys-admin-filter-row">
            <input type="text" name="search" value="{{ $filters['search'] }}" class="ys-admin-input" placeholder="Search SKU, variant, or product">
            <select name="status" class="ys-admin-select">
                @foreach (['all' => 'All stock', 'active' => 'Active variants', 'archived' => 'Archived variants', 'low' => 'Low stock', 'out' => 'Out of stock'] as $value => $label)
                    <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="ys-admin-button-secondary">Filter</button>
        </form>

        <div class="ys-admin-table-wrap mt-5">
            <table class="ys-admin-table">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Product</th>
                        <th>Stock</th>
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
                            <td>
                                <p>On hand: {{ $inventory?->quantity_on_hand ?? 0 }}</p>
                                <p class="text-xs text-ys-ivory/38">Available: {{ $inventory?->available_quantity ?? 0 }} · Reorder: {{ $inventory?->reorder_level ?? 0 }}</p>
                            </td>
                            <td>
                                <x-admin.status-pill :tone="$tone">
                                    {{ ($inventory?->quantity_on_hand ?? 0) <= 0 ? 'out of stock' : (($inventory?->quantity_on_hand ?? 0) <= ($inventory?->reorder_level ?? 0) ? 'low stock' : 'healthy') }}
                                </x-admin.status-pill>
                            </td>
                            <td>
                                <div class="ys-admin-inline-actions justify-end">
                                    <a href="{{ route('admin.inventory.manual-import.create', ['type' => 'stock_in']) }}?variant={{ $variant->id }}" class="ys-admin-button-secondary">Stock in</a>
                                    <a href="{{ route('admin.inventory.manual-import.create', ['type' => 'stock_out']) }}?variant={{ $variant->id }}" class="ys-admin-button-secondary">Stock out</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="ys-admin-empty-panel">No inventory records matched the current filters.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-5">
            {{ $variants->links() }}
        </div>
    </section>

    <section class="ys-admin-panel" data-admin-panel>
        <div class="ys-admin-panel-heading">
            <div>
                <h2 class="ys-admin-panel-title">Recent Stock Movements</h2>
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
                        <th>Reference</th>
                        <th>Admin</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentMovements as $movement)
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
                            <td>{{ $movement->reference_number ?: '—' }}</td>
                            <td>{{ $movement->actor?->name ?? 'System' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="ys-admin-empty-panel">No stock movements recorded yet.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
