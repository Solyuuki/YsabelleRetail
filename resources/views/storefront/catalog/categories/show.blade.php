@extends('layouts.app', ['title' => $category->name])

@section('content')
    <div class="rounded-3xl border border-white/10 bg-white/5 p-8">
        <p class="text-sm uppercase tracking-[0.3em] text-amber-300">Category Detail</p>
        <h1 class="mt-3 text-3xl font-semibold text-white">{{ $category->name }}</h1>
        <p class="mt-4 max-w-3xl text-stone-300">{{ $category->description ?: 'Category detail page scaffolded for future storefront and API parity.' }}</p>
    </div>

    <section class="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($category->products as $product)
            <article class="rounded-3xl border border-white/10 bg-stone-900/70 p-6">
                <h2 class="text-xl font-semibold text-white">{{ $product->name }}</h2>
                <p class="mt-2 text-sm text-stone-300">{{ $product->short_description }}</p>
                <p class="mt-4 text-amber-300">PHP {{ number_format((float) $product->base_price, 2) }}</p>
            </article>
        @empty
            <div class="rounded-3xl border border-dashed border-white/15 bg-white/5 p-6 text-stone-300 md:col-span-2 xl:col-span-3">
                This category exists structurally, but it does not have product records yet.
            </div>
        @endforelse
    </section>
@endsection
