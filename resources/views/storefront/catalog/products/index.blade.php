@extends('layouts.app', ['title' => 'Storefront Products'])

@section('content')
    <div class="mb-8 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-sm uppercase tracking-[0.3em] text-amber-300">Storefront</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">Product Domain</h1>
            <p class="mt-3 max-w-2xl text-stone-300">This page is backed by a dedicated catalog query service, request validation, and named storefront route boundaries.</p>
        </div>

        <form method="GET" class="grid gap-3 rounded-3xl border border-white/10 bg-white/5 p-4 sm:grid-cols-[1fr_auto]">
            <input
                type="text"
                name="search"
                value="{{ $filters['search'] ?? '' }}"
                placeholder="Search products or style codes"
                class="rounded-2xl border border-white/10 bg-stone-950/70 px-4 py-3 text-sm text-white placeholder:text-stone-500 focus:border-amber-300/50 focus:outline-none"
            >
            <button class="rounded-2xl bg-amber-300 px-4 py-3 text-sm font-medium text-stone-950">Apply</button>
        </form>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($products as $product)
            <article class="rounded-3xl border border-white/10 bg-white/5 p-6">
                <p class="text-xs uppercase tracking-[0.3em] text-stone-400">{{ $product->style_code ?? 'Style pending' }}</p>
                <h2 class="mt-3 text-xl font-semibold text-white">{{ $product->name }}</h2>
                <p class="mt-2 text-sm text-stone-300">{{ $product->short_description }}</p>
                <div class="mt-5 flex items-center justify-between">
                    <span class="text-lg font-semibold text-amber-300">PHP {{ number_format((float) $product->base_price, 2) }}</span>
                    <a href="{{ route('storefront.catalog.products.show', $product) }}" class="text-sm text-white underline decoration-white/20 underline-offset-4">View</a>
                </div>
            </article>
        @empty
            <div class="rounded-3xl border border-dashed border-white/15 bg-white/5 p-6 text-stone-300 md:col-span-2 xl:col-span-3">
                No products yet. The product domain is wired, but catalog data still needs seeding or admin CRUD flows.
            </div>
        @endforelse
    </div>

    <div class="mt-8">
        {{ $products->links() }}
    </div>
@endsection
