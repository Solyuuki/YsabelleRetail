@extends('layouts.app', ['title' => 'Storefront Categories'])

@section('content')
    <div class="mb-8">
        <p class="text-sm uppercase tracking-[0.3em] text-amber-300">Storefront</p>
        <h1 class="mt-2 text-3xl font-semibold text-white">Category Domain</h1>
        <p class="mt-3 max-w-2xl text-stone-300">Public catalog browsing now has its own controller, routes, and views instead of route closures.</p>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($categories as $category)
            <a href="{{ route('storefront.catalog.categories.show', $category) }}" class="rounded-3xl border border-white/10 bg-white/5 p-6 transition hover:border-amber-300/40 hover:bg-white/10">
                <p class="text-xs uppercase tracking-[0.3em] text-stone-400">{{ $category->products_count ?? 0 }} products</p>
                <h2 class="mt-3 text-xl font-semibold text-white">{{ $category->name }}</h2>
                <p class="mt-2 text-sm text-stone-300">{{ $category->description ?: 'Category description placeholder.' }}</p>
            </a>
        @empty
            <div class="rounded-3xl border border-dashed border-white/15 bg-white/5 p-6 text-stone-300 md:col-span-2 xl:col-span-3">
                No categories yet. Seed the catalog or connect the admin catalog flows next.
            </div>
        @endforelse
    </div>

    <div class="mt-8">
        {{ $categories->links() }}
    </div>
@endsection
