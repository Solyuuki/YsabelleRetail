@extends('layouts.admin', ['title' => 'Edit Category | Ysabelle Retail'])

@section('content')
    <x-admin.page-header
        eyebrow="Categories"
        :title="'Edit '.$category->name"
        description="Adjust visibility, sort order, and descriptive merchandising for this category."
    >
        <form action="{{ route('admin.catalog.categories.destroy', $category) }}" method="POST" data-confirm-message="Delete this category? Active products will block deletion.">
            @csrf
            @method('DELETE')
            <button type="submit" class="ys-admin-button-danger">Delete category</button>
        </form>
    </x-admin.page-header>

    <div class="ys-admin-panel" data-admin-panel>
        <div class="ys-admin-inline-actions text-sm text-ys-ivory/55">
            <span>{{ $category->products_count }} total product(s)</span>
            <span>{{ $category->active_products_count }} active product(s)</span>
        </div>
    </div>

    @include('admin.catalog.categories._form', [
        'action' => route('admin.catalog.categories.update', $category),
        'method' => 'PUT',
        'submitLabel' => 'Save changes',
    ])
@endsection
