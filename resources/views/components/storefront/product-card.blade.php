@props([
    'product',
    'showCategory' => true,
])

@inject('media', 'App\\Support\\Storefront\\ProductMediaResolver')

@php
    $imageUrl = $media->imageUrlFor($product);
    $imageAlt = $media->altTextFor($product);
@endphp

<article class="group overflow-hidden rounded-[1.75rem] border border-white/7 bg-ys-panel/90 shadow-[0_10px_60px_rgba(0,0,0,0.35)] transition duration-500 hover:-translate-y-1 hover:border-ys-gold/30 hover:shadow-[0_18px_75px_rgba(0,0,0,0.55)]" data-reveal>
    <a href="{{ route('storefront.catalog.products.show', $product) }}" class="block">
        <div class="relative aspect-[4/4.2] overflow-hidden border-b border-white/6 bg-black">
            <x-storefront.product-media
                :image-url="$imageUrl"
                :alt="$imageAlt"
                :title="$product->name"
                :eyebrow="$product->category?->name ?? 'Collection'"
                image-class="group-hover:scale-[1.035]"
                class="h-full w-full"
            />

            <div class="absolute left-5 top-5 flex gap-2">
                @if ($product->is_featured)
                    <span class="ys-status-pill bg-ys-gold text-ys-ink">New</span>
                @endif
                @if ($product->compare_at_price)
                    <span class="ys-status-pill bg-[#e44040] text-white">Sale</span>
                @endif
            </div>
        </div>

        <div class="space-y-4.5 p-6">
            <div class="flex items-start justify-between gap-5">
                <div>
                    <h3 class="text-[1.18rem] font-semibold leading-6 text-ys-ivory transition group-hover:text-ys-gold">{{ $product->name }}</h3>
                    @if ($showCategory)
                        <p class="mt-1.5 text-[0.72rem] uppercase tracking-[0.3em] text-ys-ivory/42">{{ $product->category?->name ?? 'Collection' }}</p>
                    @endif
                </div>

                <div class="flex items-center gap-1.5 text-[0.95rem] text-ys-gold">
                    <svg class="h-4 w-4 fill-current" viewBox="0 0 20 20">
                        <path d="m10 1.7 2.52 5.1 5.63.82-4.08 3.98.96 5.62L10 14.54l-5.03 2.65.96-5.62L1.85 7.6l5.63-.82L10 1.7Z" />
                    </svg>
                    <span>{{ number_format((float) $product->rating_average, 1) }}</span>
                </div>
            </div>

            <div class="flex items-end justify-between gap-5">
                <div>
                    <p class="text-[1.2rem] font-semibold text-ys-ivory">&#8369;{{ number_format((float) $product->base_price, 0) }}</p>
                    @if ($product->compare_at_price)
                        <p class="mt-1 text-[0.96rem] text-ys-ivory/35 line-through">&#8369;{{ number_format((float) $product->compare_at_price, 0) }}</p>
                    @endif
                </div>

                <span class="text-[0.76rem] font-semibold uppercase tracking-[0.28em] text-ys-ivory/42 transition group-hover:text-ys-gold">Explore</span>
            </div>
        </div>
    </a>
</article>
