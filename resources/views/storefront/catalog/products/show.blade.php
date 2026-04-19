@extends('layouts.storefront', ['title' => "{$product->name} | Ysabelle Retail"])

@inject('media', 'App\\Support\\Storefront\\ProductMediaResolver')

@section('content')
    @php
        $imageUrl = $media->imageUrlFor($product);
        $imageAlt = $media->altTextFor($product);
    @endphp

    <section class="ys-container pb-18 pt-10 lg:pt-14">
        <div class="mb-8 flex items-center gap-3 text-xs text-ys-ivory/38">
            <a href="{{ route('storefront.home') }}" class="transition hover:text-ys-gold">Home</a>
            <span>&rsaquo;</span>
            <a href="{{ route('storefront.shop') }}" class="transition hover:text-ys-gold">Shop</a>
            <span>&rsaquo;</span>
            <span class="text-ys-ivory/65">{{ $product->name }}</span>
        </div>

        <div class="grid gap-10 lg:grid-cols-[1.02fr_0.98fr] xl:items-start">
            <div class="overflow-hidden rounded-[2rem] border border-white/7 bg-black shadow-[0_24px_90px_rgba(0,0,0,0.52)]" data-reveal>
                <x-storefront.product-media
                    :image-url="$imageUrl"
                    :alt="$imageAlt"
                    :title="$product->name"
                    :eyebrow="$product->category?->name ?? 'Collection'"
                    loading="eager"
                    fetchpriority="high"
                    class="aspect-[1/1] h-full w-full"
                />
            </div>

            <div class="max-w-xl" data-reveal>
                <p class="text-xs font-semibold uppercase tracking-[0.35em] text-ys-gold/90">{{ $product->category?->name ?? 'Collection' }}</p>
                <h1 class="mt-4 font-serif text-5xl leading-none text-ys-ivory md:text-6xl">{{ $product->name }}</h1>

                <div class="mt-5 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-ys-ivory/55">
                    <span class="inline-flex items-center gap-1.5 text-ys-gold">
                        <svg class="h-3.5 w-3.5 fill-current" viewBox="0 0 20 20">
                            <path d="m10 1.7 2.52 5.1 5.63.82-4.08 3.98.96 5.62L10 14.54l-5.03 2.65.96-5.62L1.85 7.6l5.63-.82L10 1.7Z" />
                        </svg>
                        {{ number_format((float) $product->rating_average, 1) }}
                    </span>
                    <span>({{ $product->review_count }} reviews)</span>
                    <span>&middot;</span>
                    <span>{{ $product->variants->sum(fn ($variant) => $variant->inventoryItem?->available_quantity ?? 0) }} in stock</span>
                </div>

                <div class="mt-6 flex items-center gap-3">
                    <p class="text-4xl font-semibold text-ys-gold">&#8369;{{ number_format((float) $product->base_price, 0) }}</p>
                    @if ($product->compare_at_price)
                        <p class="text-xl text-ys-ivory/28 line-through">&#8369;{{ number_format((float) $product->compare_at_price, 0) }}</p>
                    @endif
                </div>

                <p class="mt-8 text-base leading-8 text-ys-ivory/58">{{ $product->description }}</p>

                <form action="{{ route('storefront.cart.store') }}" method="POST" class="mt-10 space-y-8" data-product-form>
                    @csrf
                    <input type="hidden" name="variant_id" value="{{ old('variant_id') }}">
                    <input type="hidden" name="quantity" value="{{ old('quantity', 1) }}" data-quantity-input>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.35em] text-ys-ivory/45">Select Size (US)</p>
                        <div class="mt-4 flex flex-wrap gap-3">
                            @foreach ($product->variants as $variant)
                                @php
                                    $size = $variant->option_values['size'] ?? preg_replace('/[^0-9.]/', '', $variant->name);
                                    $selected = (string) old('variant_id') === (string) $variant->id;
                                @endphp
                                <button
                                    type="button"
                                    class="ys-size-option {{ $selected ? 'ys-size-option-active' : '' }}"
                                    data-variant-option
                                    data-variant-id="{{ $variant->id }}"
                                >
                                    {{ $size }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.35em] text-ys-ivory/45">Quantity</p>
                        <div class="mt-4 inline-flex items-center rounded-2xl border border-white/10 bg-white/[0.02] p-1">
                            <button type="button" class="ys-quantity-button" data-quantity-step="-1">&minus;</button>
                            <span class="inline-flex min-w-12 items-center justify-center text-sm font-semibold text-ys-ivory" data-quantity-display>{{ old('quantity', 1) }}</span>
                            <button type="button" class="ys-quantity-button" data-quantity-step="1">+</button>
                        </div>
                    </div>

                    <button type="submit" class="ys-button-primary w-full justify-center text-base" data-add-to-cart-button>
                        {{ old('variant_id') ? 'Add to cart' : 'Select a size' }}
                    </button>
                </form>

                <div class="mt-8 grid gap-4 border-t border-white/7 pt-7 sm:grid-cols-3">
                    @foreach ($storefrontTrustMarks as $mark)
                        <div class="text-sm">
                            <p class="font-semibold text-ys-ivory">{{ $mark['label'] }}</p>
                            <p class="mt-1 text-xs text-ys-ivory/45">{{ $mark['description'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="ys-container pb-18 lg:pb-24">
        <div class="flex items-end justify-between gap-4">
            <x-storefront.section-heading
                eyebrow="You May Also Like"
                title="Related silhouettes"
                description="More products from the same category, surfaced through the same premium card system."
            />
        </div>

        <div class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($relatedProducts as $relatedProduct)
                <x-storefront.product-card :product="$relatedProduct" />
            @endforeach
        </div>
    </section>
@endsection
