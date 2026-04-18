@extends('layouts.app', ['title' => 'Ysabelle Store | Foundation'])

@section('content')
    <section class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
        <div class="rounded-3xl border border-white/10 bg-white/5 p-8 shadow-2xl shadow-stone-950/40">
            <p class="text-sm uppercase tracking-[0.3em] text-amber-300">Company-grade foundation</p>
            <h1 class="mt-4 max-w-2xl text-4xl font-semibold tracking-tight text-white">Ysabelle Store now has a structured Laravel foundation for retail growth.</h1>
            <p class="mt-4 max-w-2xl text-base leading-7 text-stone-300">
                This landing page is no longer the default Laravel starter. It now reflects the actual application shape:
                separated storefront, auth, admin, and API routing with core retail domains scaffolded behind them.
            </p>

            <div class="mt-8 flex flex-wrap gap-3 text-sm">
                <a href="{{ route('storefront.catalog.products.index') }}" class="rounded-full bg-amber-300 px-5 py-3 font-medium text-stone-950 transition hover:bg-amber-200">Browse Product Domain</a>
                <a href="{{ route('api.v1.status') }}" class="rounded-full border border-white/15 px-5 py-3 text-white transition hover:border-white/30">Open API Status</a>
            </div>
        </div>

        <div class="rounded-3xl border border-white/10 bg-stone-900/80 p-8">
            <h2 class="text-lg font-semibold text-white">Runtime Snapshot</h2>
            <dl class="mt-6 space-y-4">
                <div class="flex items-center justify-between rounded-2xl border border-white/5 bg-white/5 px-4 py-3">
                    <dt class="text-stone-300">Database Ready</dt>
                    <dd class="font-medium {{ $metrics['database_ready'] ? 'text-emerald-300' : 'text-amber-300' }}">
                        {{ $metrics['database_ready'] ? 'Yes' : 'Pending migration/database access' }}
                    </dd>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-2xl border border-white/5 bg-white/5 p-4">
                        <p class="text-sm text-stone-400">Categories</p>
                        <p class="mt-2 text-3xl font-semibold text-white">{{ $metrics['categories_count'] }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/5 bg-white/5 p-4">
                        <p class="text-sm text-stone-400">Products</p>
                        <p class="mt-2 text-3xl font-semibold text-white">{{ $metrics['products_count'] }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/5 bg-white/5 p-4">
                        <p class="text-sm text-stone-400">Variants</p>
                        <p class="mt-2 text-3xl font-semibold text-white">{{ $metrics['variants_count'] }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/5 bg-white/5 p-4">
                        <p class="text-sm text-stone-400">Orders</p>
                        <p class="mt-2 text-3xl font-semibold text-white">{{ $metrics['orders_count'] }}</p>
                    </div>
                </div>
            </dl>
        </div>
    </section>

    <section class="mt-10">
        <div class="mb-5 flex items-center justify-between">
            <div>
                <p class="text-sm uppercase tracking-[0.3em] text-stone-400">Catalog Preview</p>
                <h2 class="mt-2 text-2xl font-semibold text-white">Featured product domain output</h2>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($featuredProducts as $product)
                <article class="rounded-3xl border border-white/10 bg-white/5 p-6">
                    <p class="text-xs uppercase tracking-[0.3em] text-stone-500">{{ $product->category?->name ?? 'Uncategorized' }}</p>
                    <h3 class="mt-3 text-xl font-semibold text-white">{{ $product->name }}</h3>
                    <p class="mt-2 text-sm text-stone-300">{{ $product->short_description }}</p>
                    <div class="mt-6 flex items-center justify-between">
                        <span class="text-lg font-semibold text-amber-300">PHP {{ number_format((float) $product->base_price, 2) }}</span>
                        <a href="{{ route('storefront.catalog.products.show', $product) }}" class="text-sm text-white underline decoration-white/30 underline-offset-4">View product</a>
                    </div>
                </article>
            @empty
                <div class="rounded-3xl border border-dashed border-white/15 bg-white/5 p-6 text-stone-300 md:col-span-2 xl:col-span-3">
                    Catalog tables are not seeded yet, but the product domain and retrieval services are already wired.
                </div>
            @endforelse
        </div>
    </section>
@endsection
