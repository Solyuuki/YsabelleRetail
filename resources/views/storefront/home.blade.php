@extends('layouts.storefront', ['title' => 'Ysabelle Retail | Premium Footwear'])

@inject('media', 'App\\Support\\Storefront\\ProductMediaResolver')

@section('content')
    @php
        $heroImageUrl = $media->imageUrlFor($heroProduct);
        $heroImageAlt = $media->altTextFor($heroProduct);
        $heroCategory = $heroProduct?->category;
        $heroStock = $heroProduct?->variants?->sum(fn ($variant) => $variant->inventoryItem?->available_quantity ?? 0) ?? 0;
    @endphp

    <section class="ys-container pb-16 pt-10 lg:pb-24 lg:pt-14">
        <div class="grid items-center gap-14 lg:grid-cols-[0.95fr_1.05fr] xl:gap-16">
            <div class="max-w-[36rem]" data-reveal>
                @if ($heroProduct)
                    <p class="text-[0.82rem] font-semibold uppercase tracking-[0.4em] text-ys-gold/85">
                        Featured Product &middot; {{ $heroCategory?->name ?? 'Collection' }}
                    </p>
                    <h1 class="ys-hero-heading">
                        {{ $heroProduct->name }}
                        <span class="ys-hero-heading-break"></span>
                        <span class="ys-hero-heading-emphasis-group">
                            built for a <span class="ys-hero-heading-emphasis">refined</span> stride.
                        </span>
                    </h1>
                    <p class="mt-8 max-w-xl text-[1.08rem] leading-9 text-ys-ivory/58">
                        Premium footwear engineered for movement and crafted for legacy.
                        {{ $heroProduct->short_description ?: 'Every pair, a quiet statement of intention.' }}
                    </p>
                @else
                    <p class="text-[0.82rem] font-semibold uppercase tracking-[0.4em] text-ys-gold/85">New Season &middot; 2026</p>
                    <h1 class="ys-hero-heading">
                        Step into a
                        <span class="ys-hero-heading-break"></span>
                        <span class="ys-hero-heading-emphasis-group">
                            <span class="ys-hero-heading-emphasis">refined</span> stride.
                        </span>
                    </h1>
                    <p class="mt-8 max-w-xl text-[1.08rem] leading-9 text-ys-ivory/58">
                        Premium footwear engineered for movement and crafted for legacy. Every pair, a quiet statement of intention.
                    </p>
                @endif

                <div class="mt-11 flex flex-wrap gap-4">
                    <a href="{{ $heroProduct ? route('storefront.catalog.products.show', $heroProduct) : route('storefront.shop') }}" class="ys-button-primary">
                        {{ $heroProduct ? "Shop {$heroProduct->name}" : 'Shop the Collection' }}
                    </a>
                    <a
                        href="{{ $heroCategory ? route('storefront.shop', ['category' => $heroCategory->slug]) : route('storefront.shop', ['category' => 'running']) }}"
                        class="ys-button-secondary"
                    >
                        {{ $heroCategory ? "Explore {$heroCategory->name}" : 'Explore Running' }}
                    </a>
                </div>

                <div class="mt-12 grid max-w-lg grid-cols-3 gap-8">
                    <div class="ys-stat-block">
                        <p class="ys-stat-value">{{ $heroProduct ? 'PHP '.number_format((float) $heroProduct->base_price, 0) : '20+' }}</p>
                        <p class="ys-stat-label">{{ $heroProduct ? 'Hero price' : 'Premium styles' }}</p>
                    </div>
                    <div class="ys-stat-block">
                        <p class="ys-stat-value">{{ $heroProduct ? number_format((float) $heroProduct->rating_average, 1).' rating' : '14d' }}</p>
                        <p class="ys-stat-label">{{ $heroProduct ? 'Rated by clients' : 'Free returns' }}</p>
                    </div>
                    <div class="ys-stat-block">
                        <p class="ys-stat-value">{{ $heroProduct ? $heroStock : '4.9 rating' }}</p>
                        <p class="ys-stat-label">{{ $heroProduct ? 'Units in stock' : 'Rated by clients' }}</p>
                    </div>
                </div>
            </div>

            <div class="relative" data-reveal>
                <div class="absolute inset-6 rounded-full bg-ys-gold/18 blur-3xl"></div>
                <div class="relative overflow-hidden rounded-[2.5rem] border border-white/7 bg-black p-5 shadow-[0_35px_120px_rgba(0,0,0,0.62)] lg:p-6">
                    <x-storefront.product-media
                        :image-url="$heroImageUrl"
                        :alt="$heroImageAlt"
                        :title="$heroProduct?->name ?? 'Curated product imagery'"
                        :eyebrow="$heroCategory?->name ?? 'Ysabelle Retail'"
                        loading="eager"
                        fetchpriority="high"
                        class="aspect-[4/4.35] rounded-[2rem]"
                        fallback-class="p-7 lg:p-9"
                    />

                    @if ($heroProduct)
                        <div class="pointer-events-none absolute inset-x-9 bottom-9 rounded-[1.55rem] border border-white/9 bg-black/62 px-5 py-4 backdrop-blur-xl lg:inset-x-10 lg:bottom-10">
                            <div class="flex items-end justify-between gap-4">
                                <div>
                                    <p class="text-[0.72rem] font-semibold uppercase tracking-[0.3em] text-ys-gold/78">{{ $heroCategory?->name ?? 'Collection' }}</p>
                                    <p class="mt-2 font-serif text-[1.8rem] leading-none text-ys-ivory">{{ $heroProduct->name }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[0.72rem] font-semibold uppercase tracking-[0.22em] text-ys-ivory/42">Starting at</p>
                                    <p class="mt-2 text-[1.2rem] font-semibold text-ys-gold">PHP {{ number_format((float) $heroProduct->base_price, 0) }}</p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <x-storefront.trust-strip />

    <section class="ys-container py-20 lg:py-28">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <x-storefront.section-heading
                eyebrow="Featured Collection"
                title="Crafted for motion, tuned for presence."
                description="A premium edit of the silhouettes customers are already gravitating toward, presented in the same dark editorial language from the reference storefront."
            />
            <a href="{{ route('storefront.shop') }}" class="ys-link-inline">Browse all shoes</a>
        </div>

        <div class="mt-12 grid gap-6 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($featuredProducts->take(4) as $product)
                <x-storefront.product-card :product="$product" />
            @endforeach
        </div>
    </section>

    <section class="ys-container pb-20 lg:pb-28">
        <div class="grid gap-7 xl:grid-cols-[1.1fr_0.9fr]">
            <div class="rounded-[2.15rem] border border-white/7 bg-gradient-to-br from-ys-panel via-[#1a1713] to-[#15120f] p-9 md:p-12" data-reveal>
                <p class="text-[0.82rem] font-semibold uppercase tracking-[0.35em] text-ys-gold/90">Editorial Drop</p>
                <h2 class="mt-4 max-w-xl font-serif text-[3.45rem] leading-[0.96] text-ys-ivory">Quiet luxury, built for everyday velocity.</h2>
                <p class="mt-6 max-w-2xl text-[1rem] leading-8 text-ys-ivory/58">
                    The premium UI language in the reference leans on restraint, contrast, and a product-first composition. This middle callout preserves that same posture while staying clean inside Laravel.
                </p>
                <div class="mt-9 flex flex-wrap gap-4">
                    <a href="{{ route('storefront.shop', ['category' => 'running']) }}" class="ys-button-primary">Explore bestsellers</a>
                    <a href="{{ route('storefront.account.index') }}" class="ys-button-secondary">View your orders</a>
                </div>
            </div>

            <div class="grid gap-6 sm:grid-cols-2" data-reveal>
                @foreach ($featuredCategories->take(4) as $category)
                    <a href="{{ route('storefront.shop', ['category' => $category->slug]) }}" class="rounded-[1.95rem] border border-white/7 bg-white/[0.02] p-7 transition duration-500 hover:border-ys-gold/30 hover:bg-white/[0.035]">
                        <p class="text-[0.78rem] font-semibold uppercase tracking-[0.3em] text-ys-gold/80">Category</p>
                        <h3 class="mt-4 font-serif text-[2.15rem] leading-none text-ys-ivory">{{ $category->name }}</h3>
                        <p class="mt-4 text-[0.98rem] leading-8 text-ys-ivory/50">{{ $category->description }}</p>
                        <span class="mt-7 inline-flex text-[0.98rem] font-semibold text-ys-ivory/70 transition hover:text-ys-gold">Open collection</span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endsection
