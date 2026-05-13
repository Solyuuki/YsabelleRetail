@extends('layouts.admin', ['title' => 'Edit Product | Ysabelle Retail'])

@section('content')
    <x-admin.page-header
        eyebrow="Products"
        :title="'Edit '.$product->name"
        description="Refine storefront presentation, stock behavior, and variant data without leaving the admin workspace."
    >
        <span class="ys-admin-pill {{ ($deletionAssessment['can_delete'] ?? false) ? 'ys-admin-pill-success' : 'ys-admin-pill-warning' }}">
            {{ ($deletionAssessment['can_delete'] ?? false) ? 'Delete available' : 'Archive recommended' }}
        </span>
    </x-admin.page-header>

    @include('admin.catalog.products._form', [
        'action' => route('admin.catalog.products.update', $product),
        'method' => 'PUT',
        'submitLabel' => 'Save changes',
        'visibilityDiagnostics' => $visibilityDiagnostics,
        'deletionAssessment' => $deletionAssessment,
    ])
@endsection
