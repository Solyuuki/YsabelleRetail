@extends('layouts.storefront', ['title' => 'Ysabelle Retail | Premium Footwear'])

@section('content')
    @php
        $heroCategory = $heroProduct?->category;
        $heroShowcaseName = 'Nike Shoe V2';
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

    <x-storefront.featured-showcase :products="$featuredProducts" />
@endsection
