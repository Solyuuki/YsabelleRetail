@extends('layouts.storefront', ['title' => 'Ysabelle Retail | Premium Footwear'])

@section('content')
    <section class="ys-container pb-14 pt-8 lg:pb-20 lg:pt-12">
        <div class="grid items-center gap-12 lg:grid-cols-[0.92fr_1.08fr]">
            <div class="max-w-xl" data-reveal>
                <p class="text-xs font-semibold uppercase tracking-[0.42em] text-ys-gold/85">New Season &middot; 2026</p>
                <h1 class="mt-7 font-serif text-6xl leading-[0.92] text-ys-ivory sm:text-7xl xl:text-[5.4rem]">
                    Step into a <span class="text-ys-gold italic">refined</span> stride.
                </h1>
                <p class="mt-7 max-w-lg text-base leading-8 text-ys-ivory/58">
                    Premium footwear engineered for movement and crafted for legacy. Every pair, a quiet statement of intention.
                </p>

                <div class="mt-10 flex flex-wrap gap-3">
                    <a href="{{ route('storefront.shop') }}" class="ys-button-primary">Shop the Collection</a>
                    <a href="{{ route('storefront.shop', ['category' => 'running']) }}" class="ys-button-secondary">Explore Running</a>
                </div>

                <div class="mt-10 grid max-w-md grid-cols-3 gap-6">
                    <div class="ys-stat-block">
                        <p class="ys-stat-value">20+</p>
                        <p class="ys-stat-label">Premium styles</p>
                    </div>
                    <div class="ys-stat-block">
                        <p class="ys-stat-value">14d</p>
                        <p class="ys-stat-label">Free returns</p>
                    </div>
                    <div class="ys-stat-block">
                        <p class="ys-stat-value">4.9&#9733;</p>
                        <p class="ys-stat-label">Rated by clients</p>
                    </div>
                </div>
            </div>

            <div class="relative" data-reveal>
                <div class="absolute inset-6 rounded-full bg-ys-gold/18 blur-3xl"></div>
                <div class="relative overflow-hidden rounded-[2.2rem] border border-white/7 bg-black p-8 shadow-[0_35px_120px_rgba(0,0,0,0.62)]">
                    <img src="{{ asset('/images/storefront/products/aurum-hero.png') }}" alt="Aurum Runner hero shoe" class="h-auto w-full object-cover">
                </div>
            </div>
        </div>
    </section>

    <x-storefront.trust-strip />

    <section class="ys-container py-18 lg:py-24">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <x-storefront.section-heading
                eyebrow="Featured Collection"
                title="Crafted for motion, tuned for presence."
                description="A premium edit of the silhouettes customers are already gravitating toward, presented in the same dark editorial language from the reference storefront."
            />
            <a href="{{ route('storefront.shop') }}" class="ys-link-inline">Browse all shoes</a>
        </div>

        <div class="mt-10 grid gap-5 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($featuredProducts->take(4) as $product)
                <x-storefront.product-card :product="$product" />
            @endforeach
        </div>
    </section>

    <section class="ys-container pb-18 lg:pb-24">
        <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
            <div class="rounded-[2rem] border border-white/7 bg-gradient-to-br from-ys-panel via-[#1a1713] to-[#15120f] p-8 md:p-10" data-reveal>
                <p class="text-xs font-semibold uppercase tracking-[0.35em] text-ys-gold/90">Editorial Drop</p>
                <h2 class="mt-4 max-w-lg font-serif text-5xl leading-[0.95] text-ys-ivory">Quiet luxury, built for everyday velocity.</h2>
                <p class="mt-6 max-w-xl text-sm leading-8 text-ys-ivory/58">
                    The premium UI language in the reference leans on restraint, contrast, and a product-first composition. This middle callout preserves that same posture while staying clean inside Laravel.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('storefront.shop', ['category' => 'running']) }}" class="ys-button-primary">Explore bestsellers</a>
                    <a href="{{ route('storefront.account.index') }}" class="ys-button-secondary">View your orders</a>
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2" data-reveal>
                @foreach ($featuredCategories->take(4) as $category)
                    <a href="{{ route('storefront.shop', ['category' => $category->slug]) }}" class="rounded-[1.8rem] border border-white/7 bg-white/[0.02] p-6 transition duration-500 hover:border-ys-gold/30 hover:bg-white/[0.035]">
                        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-ys-gold/80">Category</p>
                        <h3 class="mt-4 font-serif text-3xl text-ys-ivory">{{ $category->name }}</h3>
                        <p class="mt-4 text-sm leading-7 text-ys-ivory/50">{{ $category->description }}</p>
                        <span class="mt-6 inline-flex text-sm font-semibold text-ys-ivory/70 transition hover:text-ys-gold">Open collection</span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endsection
