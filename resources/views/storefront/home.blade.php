@extends('layouts.storefront', ['title' => 'Ysabelle Retail | Premium Footwear'])

@section('content')
    @php
        $heroCategory = $heroProduct?->category;
        $heroShowcaseName = 'Nike Shoe V2';
        $heroShowcaseSourceUrl = 'https://poly.cam/explore/capture/3051A780-6C9D-45B7-A1A1-D568C3839F63/Nike+Shoe+V2';
        $heroShowcaseModelUrl = asset('models/storefront/polycam-nike-shoe-v2/poly.gltf');
        $heroShowcaseAlt = 'Nike Shoe V2 Polycam 3D hero showcase';
        $heroShowcaseEyebrow = 'Polycam Capture / Charcoal / White / Orange Accent';
        $heroShowcaseDescription = 'Nike Shoe V2 shows a charcoal mesh runner with black laces and collar, a crisp white Swoosh, a sculpted white sole, and a small orange eyelet accent taken directly from the Polycam source capture.';
        $heroShowcaseStats = [
            ['value' => 'Nike', 'label' => 'Captured shoe'],
            ['value' => '55,389', 'label' => 'Polycam vertices'],
            ['value' => 'Mar 2022', 'label' => 'Published'],
        ];
    @endphp

    <section class="ys-container pb-16 pt-14 lg:pb-24 lg:pt-20">
        <div class="grid items-center gap-14 lg:grid-cols-[0.95fr_1.05fr] xl:gap-16">
            <div class="ys-hero-copy" data-reveal>
                <p class="ys-hero-eyebrow">
                    {{ $heroShowcaseEyebrow }}
                </p>
                <h1 class="ys-hero-heading">
                    <span class="ys-hero-heading-line ys-hero-heading-line-primary">
                        {{ $heroShowcaseName }} <span class="ys-hero-heading-kicker">Built</span>
                    </span>
                    <span class="ys-hero-heading-line ys-hero-heading-line-secondary">
                        For a <span class="ys-hero-heading-emphasis">Refined</span>
                    </span>
                </h1>
                <p class="ys-hero-description">
                    {{ $heroShowcaseDescription }}
                </p>

                <div class="ys-hero-actions">
                    <a href="{{ $heroShowcaseSourceUrl }}" class="ys-button-primary" target="_blank" rel="noreferrer">
                        View Source Capture
                    </a>
                    <a
                        href="{{ $heroCategory ? route('storefront.shop', ['category' => $heroCategory->slug]) : route('storefront.shop', ['category' => 'running']) }}"
                        class="ys-button-secondary"
                    >
                        {{ $heroCategory ? "Explore {$heroCategory->name}" : 'Explore Running' }}
                    </a>
                </div>

                <div class="ys-hero-stats">
                    @foreach ($heroShowcaseStats as $heroShowcaseStat)
                        <div class="ys-stat-block">
                            <p class="ys-stat-value">{{ $heroShowcaseStat['value'] }}</p>
                            <p class="ys-stat-label">{{ $heroShowcaseStat['label'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <div
                class="ys-hero-product-shell"
                data-reveal
                data-hero-showcase
                data-model-src="{{ $heroShowcaseModelUrl }}"
            >
                <div class="ys-hero-product-glow" aria-hidden="true"></div>
                <div class="ys-hero-product-stage">
                    <div class="ys-hero-product-shadow" aria-hidden="true"></div>
                    <div class="ys-hero-product-rig">
                        <div class="ys-hero-product-fallback" aria-hidden="true"></div>
                        <div class="ys-hero-product-fallback-shadow" aria-hidden="true"></div>
                        <model-viewer
                            class="ys-hero-model-viewer"
                            data-hero-model-viewer
                            alt="{{ $heroShowcaseAlt }}"
                            reveal="auto"
                            loading="lazy"
                            poster="{{ asset('images/storefront/hero-polycam-nike-shoe-v2.jpg') }}"
                            interaction-prompt="none"
                            environment-image="{{ asset('images/storefront/hdri/small_hangar_01_1k.hdr') }}"
                            tone-mapping="neutral"
                            exposure="0.96"
                            shadow-intensity="1.16"
                            shadow-softness="1.08"
                            camera-orbit="0deg 78deg 112%"
                            min-camera-orbit="auto 74deg auto"
                            max-camera-orbit="auto 84deg auto"
                            field-of-view="24deg"
                        ></model-viewer>
                    </div>
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
