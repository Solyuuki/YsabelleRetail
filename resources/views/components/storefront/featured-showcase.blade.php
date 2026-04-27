@props([
    'products' => collect(),
])

@inject('media', 'App\\Support\\Storefront\\ProductMediaResolver')

@php
    $items = collect($products)->take(4)->values();
    $bannerDelay = 120 + ($items->count() * 70);
@endphp

<section class="ys-container py-20 lg:py-24">
    <div class="ys-featured-showcase">
        <div class="ys-featured-showcase-header" data-reveal>
            <div>
                <p class="ys-featured-showcase-eyebrow">Curated selection</p>
                <h2 class="ys-featured-showcase-title">Featured pieces</h2>
            </div>

            <a href="{{ route('storefront.shop') }}" class="ys-featured-showcase-link">
                View all
            </a>
        </div>

        <div class="ys-featured-showcase-grid">
            @forelse ($items as $index => $product)
                @php
                    $imageUrl = $media->imageUrlFor($product);
                    $imageAlt = $media->altTextFor($product);
                    $hasImage = filled($imageUrl);
                @endphp

                <article
                    class="ys-featured-card group"
                    data-reveal
                    data-reveal-delay="{{ 40 + ($index * 70) }}"
                >
                    <a href="{{ route('storefront.catalog.products.show', $product) }}" class="ys-featured-card-link">
                        <div class="ys-featured-card-media">
                            <div class="ys-featured-card-badges">
                                @if ($product->is_featured)
                                    <span class="ys-featured-card-badge is-gold">New</span>
                                @endif
                                @if ($product->compare_at_price)
                                    <span class="ys-featured-card-badge is-sale">Sale</span>
                                @endif
                            </div>

                            @if ($hasImage)
                                <img
                                    src="{{ $imageUrl }}"
                                    alt="{{ $imageAlt }}"
                                    loading="lazy"
                                    decoding="async"
                                    class="ys-featured-card-image"
                                >
                            @else
                                <div class="ys-featured-card-fallback" aria-hidden="true">
                                    <span>{{ $product->name }}</span>
                                </div>
                            @endif
                        </div>

                        <div class="ys-featured-card-body">
                            <div class="ys-featured-card-row">
                                <div class="ys-featured-card-copy">
                                    <h3 class="ys-featured-card-title">{{ $product->name }}</h3>
                                    <p class="ys-featured-card-caption">{{ $product->category?->name ?? 'Collection' }}</p>
                                </div>

                                <div class="ys-featured-card-rating" aria-label="Rated {{ number_format((float) $product->rating_average, 1) }}">
                                    <span class="ys-featured-card-rating-dot" aria-hidden="true"></span>
                                    <span>{{ number_format((float) $product->rating_average, 1) }}</span>
                                </div>
                            </div>

                            <div class="ys-featured-card-row is-bottom">
                                <div class="ys-featured-card-price-wrap">
                                    <p class="ys-featured-card-price">&#8369;{{ number_format((float) $product->base_price, 0) }}</p>
                                    <p class="ys-featured-card-price-note">{{ $product->category?->name ?? 'Collection' }}</p>
                                </div>

                                <span class="ys-featured-card-action">Explore</span>
                            </div>
                        </div>
                    </a>
                </article>
            @empty
                <div class="ys-featured-empty-state" data-reveal>
                    <p class="ys-featured-empty-state-eyebrow">Catalog pending</p>
                    <h3 class="ys-featured-empty-state-title">No featured products available yet.</h3>
                    <p class="ys-featured-empty-state-copy">
                        We are curating the next drop for this collection. Explore the catalog and check back soon for featured releases.
                    </p>
                    <a href="{{ route('storefront.shop') }}" class="ys-featured-empty-state-action">
                        Browse the catalog
                    </a>
                </div>
            @endforelse
        </div>

        <div class="ys-featured-banner" data-reveal data-reveal-delay="{{ $bannerDelay }}">
            <div class="ys-featured-banner-inner">
                <h3 class="ys-featured-banner-title">Built for those who move with intention.</h3>
                <p class="ys-featured-banner-copy">Discover our full footwear collection.</p>
                <a href="{{ route('storefront.shop') }}" class="ys-featured-banner-button">
                    Explore the collection
                </a>
            </div>
        </div>
    </div>
</section>
