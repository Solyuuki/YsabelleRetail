@extends('layouts.admin', ['title' => 'Products | Ysabelle Retail'])

@section('content')
    @php
        $quickCategories = $categories->take(7);
        $overflowCategories = $categories->slice(7)->values();
        $baseQuery = array_filter([
            'search' => $filters['search'] ?: null,
            'status' => $filters['status'] !== 'all' ? $filters['status'] : null,
        ], fn ($value) => $value !== null && $value !== '');
    @endphp

    <x-admin.page-header
        eyebrow="Catalog"
        title="Product management"
        description="Search, filter, and manage products without leaving the back office."
    >
        <a href="{{ route('admin.catalog.products.create') }}" class="ys-admin-button-primary">Create product</a>
    </x-admin.page-header>

    <section class="ys-admin-panel" data-admin-panel>
        <div class="space-y-4">
            <div class="flex flex-wrap items-center gap-2">
                <a
                    href="{{ route('admin.catalog.products.index', $baseQuery) }}"
                    class="ys-admin-tab-link {{ $filters['category_id'] ? '' : 'is-active' }}"
                >
                    All categories
                </a>

                @foreach ($quickCategories as $category)
                    <a
                        href="{{ route('admin.catalog.products.index', array_merge($baseQuery, ['category_id' => $category->id])) }}"
                        class="ys-admin-tab-link {{ (int) $filters['category_id'] === $category->id ? 'is-active' : '' }}"
                    >
                        {{ $category->name }}
                    </a>
                @endforeach

                @if ($overflowCategories->isNotEmpty())
                    <details class="ys-admin-detail-panel px-3 py-2">
                        <summary class="ys-admin-detail-summary">More categories</summary>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($overflowCategories as $category)
                                <a
                                    href="{{ route('admin.catalog.products.index', array_merge($baseQuery, ['category_id' => $category->id])) }}"
                                    class="ys-admin-tab-link {{ (int) $filters['category_id'] === $category->id ? 'is-active' : '' }}"
                                >
                                    {{ $category->name }}
                                </a>
                            @endforeach
                        </div>
                    </details>
                @endif
            </div>

            <form method="GET" class="ys-admin-toolbar">
            <input type="text" name="search" value="{{ $filters['search'] }}" class="ys-admin-input" placeholder="Search by product, style code, or SKU">
            <select name="status" class="ys-admin-select">
                @foreach (['all' => 'All statuses', 'active' => 'Active', 'draft' => 'Draft', 'archived' => 'Archived'] as $value => $label)
                    <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="category_id" class="ys-admin-select">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected($filters['category_id'] == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <button class="ys-admin-button-secondary">Filter</button>
            </form>
        </div>

        <div class="ys-admin-table-wrap mt-5">
            <table class="ys-admin-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Variants</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr>
                            <td>
                                <p class="font-semibold text-ys-ivory">{{ $product->name }}</p>
                                <p class="text-xs text-ys-ivory/38">{{ $product->style_code ?: 'No style code' }}</p>
                            </td>
                            <td>{{ $product->category?->name ?? 'Uncategorized' }}</td>
                            <td>{{ $product->variants->count() }}</td>
                            <td>PHP {{ number_format((float) $product->base_price, 2) }}</td>
                            <td>
                                <x-admin.status-pill :tone="$product->status === 'active' ? 'success' : ($product->status === 'draft' ? 'warning' : 'danger')">
                                    {{ $product->status }}
                                </x-admin.status-pill>
                            </td>
                            <td class="text-right">
                                <a href="{{ route('admin.catalog.products.edit', $product) }}" class="ys-admin-button-secondary">Manage</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="ys-admin-empty-panel">No products matched the current filters.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-5">
            {{ $products->links('vendor.pagination.admin') }}
        </div>
    </section>
@endsection
