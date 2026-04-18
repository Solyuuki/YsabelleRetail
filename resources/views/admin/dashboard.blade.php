@extends('layouts.app', ['title' => 'Admin Dashboard'])

@section('content')
    <div class="mb-8">
        <p class="text-sm uppercase tracking-[0.3em] text-amber-300">Admin</p>
        <h1 class="mt-2 text-3xl font-semibold text-white">Operational Dashboard Foundation</h1>
        <p class="mt-3 max-w-3xl text-stone-300">Admin pages now live behind dedicated admin routes and middleware boundaries instead of generic starter navigation.</p>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            'Categories' => $metrics['categories_count'],
            'Products' => $metrics['products_count'],
            'Variants' => $metrics['variants_count'],
            'Orders' => $metrics['orders_count'],
        ] as $label => $value)
            <div class="rounded-3xl border border-white/10 bg-white/5 p-6">
                <p class="text-sm text-stone-400">{{ $label }}</p>
                <p class="mt-2 text-3xl font-semibold text-white">{{ $value }}</p>
            </div>
        @endforeach
    </div>
@endsection
