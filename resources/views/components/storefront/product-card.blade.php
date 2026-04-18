@props([
    'product',
    'showCategory' => true,
])

@inject('media', 'App\\Support\\Storefront\\ProductMediaResolver')

<article class="group overflow-hidden rounded-[1.6rem] border border-white/7 bg-ys-panel/90 shadow-[0_10px_60px_rgba(0,0,0,0.35)] transition duration-500 hover:-translate-y-1 hover:border-ys-gold/30 hover:shadow-[0_18px_75px_rgba(0,0,0,0.55)]" data-reveal>
    <a href="{{ route('storefront.catalog.products.show', $product) }}" class="block">
        <div class="relative aspect-[4/4.2] overflow-hidden border-b border-white/6 bg-black">
            <img
                src="{{ $media->pathFor($product, 'card') }}"
                alt="{{ $product->name }}"
                class="h-full w-full object-cover transition duration-700 group-hover:scale-[1.035]"
            >

            <div class="absolute left-4 top-4 flex gap-2">
                @if ($product->is_featured)
                    <span class="ys-status-pill bg-ys-gold text-ys-ink">New</span>
                @endif
                @if ($product->compare_at_price)
                    <span class="ys-status-pill bg-[#e44040] text-white">Sale</span>
                @endif
            </div>
        </div>

        <div class="space-y-4 p-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-ys-ivory transition group-hover:text-ys-gold">{{ $product->name }}</h3>
                    @if ($showCategory)
                        <p class="mt-1 text-[11px] uppercase tracking-[0.3em] text-ys-ivory/42">{{ $product->category?->name ?? 'Collection' }}</p>
                    @endif
                </div>

                <div class="flex items-center gap-1.5 text-sm text-ys-gold">
                    <svg class="h-3.5 w-3.5 fill-current" viewBox="0 0 20 20">
                        <path d="m10 1.7 2.52 5.1 5.63.82-4.08 3.98.96 5.62L10 14.54l-5.03 2.65.96-5.62L1.85 7.6l5.63-.82L10 1.7Z" />
                    </svg>
                    <span>{{ number_format((float) $product->rating_average, 1) }}</span>
                </div>
            </div>

            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="text-lg font-semibold text-ys-ivory">&#8369;{{ number_format((float) $product->base_price, 0) }}</p>
                    @if ($product->compare_at_price)
                        <p class="text-sm text-ys-ivory/35 line-through">&#8369;{{ number_format((float) $product->compare_at_price, 0) }}</p>
                    @endif
                </div>

                <span class="text-xs font-semibold uppercase tracking-[0.28em] text-ys-ivory/42 transition group-hover:text-ys-gold">Explore</span>
            </div>
        </div>
    </a>
</article>
