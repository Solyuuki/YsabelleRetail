@extends('layouts.admin', ['title' => 'Categories | Ysabelle Retail'])

@section('content')
    <x-admin.page-header
        eyebrow="Catalog"
        title="Category management"
        description="Maintain clean product groupings and safe category rules."
    >
        <a href="{{ route('admin.catalog.categories.create') }}" class="ys-admin-button-primary">Create category</a>
    </x-admin.page-header>

    <section class="ys-admin-panel" data-admin-panel>
        <form method="GET" class="ys-admin-toolbar">
            <input type="text" name="search" value="{{ $search }}" class="ys-admin-input" placeholder="Search categories">
            <button class="ys-admin-button-secondary">Filter</button>
        </form>

        <div class="ys-admin-table-wrap mt-5">
            <table class="ys-admin-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Products</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr>
                            <td>
                                <p class="font-semibold text-ys-ivory">{{ $category->name }}</p>
                                <p class="text-xs text-ys-ivory/38">{{ $category->slug }}</p>
                            </td>
                            <td>
                                <p>{{ $category->products_count }} total</p>
                                <p class="text-xs text-ys-ivory/38">{{ $category->active_products_count }} active</p>
                            </td>
                            <td>
                                <x-admin.status-pill :tone="$category->is_active ? 'success' : 'danger'">
                                    {{ $category->is_active ? 'active' : 'inactive' }}
                                </x-admin.status-pill>
                            </td>
                            <td class="text-right">
                                <a href="{{ route('admin.catalog.categories.edit', $category) }}" class="ys-admin-button-secondary">Manage</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <div class="ys-admin-empty-panel">No categories matched the current search.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-5">
            {{ $categories->links('vendor.pagination.admin') }}
        </div>
    </section>
@endsection
