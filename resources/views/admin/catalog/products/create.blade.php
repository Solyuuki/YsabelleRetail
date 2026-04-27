@extends('layouts.admin', ['title' => 'Create Product | Ysabelle Retail'])

@section('content')
    <x-admin.page-header
        eyebrow="Products"
        title="Create product"
        description="Add a product with structured variants, stock defaults, and storefront-ready merchandising."
    />

    @include('admin.catalog.products._form', [
        'action' => route('admin.catalog.products.store'),
        'submitLabel' => 'Create product',
    ])
@endsection
