@extends('layouts.admin', ['title' => 'Edit Product | Ysabelle Retail'])

@section('content')
    <x-admin.page-header
        eyebrow="Products"
        :title="'Edit '.$product->name"
        description="Refine storefront presentation, stock behavior, and variant data without leaving the admin workspace."
    >
        <form action="{{ route('admin.catalog.products.destroy', $product) }}" method="POST" data-confirm-message="Archive this product?">
            @csrf
            @method('DELETE')
            <button type="submit" class="ys-admin-button-danger">Archive product</button>
        </form>
    </x-admin.page-header>

    @include('admin.catalog.products._form', [
        'action' => route('admin.catalog.products.update', $product),
        'method' => 'PUT',
        'submitLabel' => 'Save changes',
    ])
@endsection
