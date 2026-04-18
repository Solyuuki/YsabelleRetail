@extends('layouts.app', ['title' => $product->name])

@section('content')
    <div class="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
        <section class="rounded-3xl border border-white/10 bg-white/5 p-8">
            <p class="text-sm uppercase tracking-[0.3em] text-amber-300">{{ $product->category?->name ?? 'Uncategorized' }}</p>
            <h1 class="mt-3 text-4xl font-semibold text-white">{{ $product->name }}</h1>
            <p class="mt-4 text-lg text-amber-300">PHP {{ number_format((float) $product->base_price, 2) }}</p>
            <p class="mt-6 max-w-3xl text-stone-300">{{ $product->description ?: $product->short_description ?: 'Product detail content will expand as media, merchandising, and recommendation data are implemented.' }}</p>
        </section>

        <aside class="rounded-3xl border border-white/10 bg-stone-900/80 p-8">
            <h2 class="text-lg font-semibold text-white">Variants & Inventory</h2>
            <div class="mt-5 space-y-3">
                @forelse ($product->variants as $variant)
                    <div class="rounded-2xl border border-white/5 bg-white/5 p-4">
                        <div class="flex items-center justify-between">
                            <p class="font-medium text-white">{{ $variant->name }}</p>
                            <p class="text-sm text-stone-400">{{ $variant->sku }}</p>
                        </div>
                        <p class="mt-2 text-sm text-stone-300">
                            Available: {{ $variant->inventoryItem?->available_quantity ?? 0 }}
                            @if ($variant->inventoryItem?->allow_backorder)
                                <span class="ml-2 text-amber-300">Backorders enabled</span>
                            @endif
                        </p>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-white/15 bg-white/5 p-4 text-sm text-stone-300">
                        Variant scaffolding is in place, but this product has no variant records yet.
                    </div>
                @endforelse
            </div>
        </aside>
    </div>
@endsection
