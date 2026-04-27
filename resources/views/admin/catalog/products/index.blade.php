@extends('layouts.admin', ['title' => 'Products | Ysabelle Retail'])

@section('content')
    <x-admin.page-header
        eyebrow="Catalog"
        title="Product management"
        description="Search, filter, create, and safely archive products while keeping variants and inventory aligned."
    >
        <a href="{{ route('admin.catalog.products.create') }}" class="ys-admin-button-primary">Create product</a>
    </x-admin.page-header>

    <section class="ys-admin-panel" data-admin-panel>
        <form method="GET" class="ys-admin-filter-row">
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
            {{ $products->links() }}
        </div>
    </section>
@endsection
