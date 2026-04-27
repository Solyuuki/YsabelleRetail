@extends('layouts.admin', ['title' => 'Create Category | Ysabelle Retail'])

@section('content')
    <x-admin.page-header
        eyebrow="Categories"
        title="Create category"
        description="Build reusable storefront groupings without breaking existing product relationships."
    />

    @include('admin.catalog.categories._form', [
        'action' => route('admin.catalog.categories.store'),
        'submitLabel' => 'Create category',
    ])
@endsection
