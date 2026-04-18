@extends('layouts.app', ['title' => 'Admin Products'])

@section('content')
    <div class="mb-8">
        <p class="text-sm uppercase tracking-[0.3em] text-amber-300">Admin Catalog</p>
        <h1 class="mt-2 text-3xl font-semibold text-white">Product Operations</h1>
        <p class="mt-3 text-stone-300">Product management now has a dedicated admin controller and route namespace ready for real CRUD, policies, and inventory actions.</p>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($products as $product)
            <article class="rounded-3xl border border-white/10 bg-white/5 p-6">
                <p class="text-xs uppercase tracking-[0.25em] text-stone-400">{{ $product->style_code }}</p>
                <h2 class="mt-3 text-xl font-semibold text-white">{{ $product->name }}</h2>
                <p class="mt-2 text-sm text-stone-300">{{ $product->category?->name ?? 'Uncategorized' }}</p>
                <p class="mt-4 text-amber-300">PHP {{ number_format((float) $product->base_price, 2) }}</p>
            </article>
        @empty
            <div class="rounded-3xl border border-dashed border-white/15 bg-white/5 p-6 text-stone-300 md:col-span-2 xl:col-span-3">
                Product management routes are in place, but no catalog records exist yet.
            </div>
        @endforelse
    </div>

    <div class="mt-8">
        {{ $products->links() }}
    </div>
@endsection
