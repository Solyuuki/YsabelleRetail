@extends('layouts.storefront', ['title' => 'Shop All Shoes | Ysabelle Retail'])

@section('content')
    <section class="ys-container pb-18 pt-10 lg:pt-14">
        <x-storefront.section-heading
            eyebrow="The Collection"
            title="Shop all shoes"
            description="Engineered for performance. Designed for legacy."
        />

        <div class="ys-storefront-toolbar mt-10" data-reveal>
            <form method="GET" action="{{ route('storefront.shop') }}" class="ys-storefront-toolbar-form" data-product-browse-form>
                @foreach (collect($filters)->except(['search', 'min_price', 'max_price', 'sort', 'page', 'per_page'])->filter(fn ($value) => filled($value)) as $filterKey => $filterValue)
                    <input type="hidden" name="{{ $filterKey }}" value="{{ $filterValue }}">
                @endforeach
                <input type="hidden" name="per_page" value="{{ $filters['per_page'] ?? 12 }}" data-responsive-per-page>

                <div class="ys-storefront-toolbar-main">
                    <label class="ys-storefront-search-field">
                        <span class="ys-storefront-search-icon" aria-hidden="true">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                <circle cx="11" cy="11" r="6.5" />
                                <path d="m16 16 4.5 4.5" stroke-linecap="round" />
                            </svg>
                        </span>
                        <input
                            type="text"
                            name="search"
                            value="{{ $filters['search'] ?? '' }}"
                            class="ys-input ys-storefront-search-input"
                            placeholder="Search shoes, brands, category, color, SKU, or style..."
                        >
                    </label>

                    <div class="ys-storefront-sort-wrap">
                        <select name="sort" class="ys-select ys-storefront-sort-select" data-auto-submit>
                            <option value="featured" @selected(($filters['sort'] ?? 'featured') === 'featured')>Featured</option>
                            <option value="price_asc" @selected(($filters['sort'] ?? null) === 'price_asc')>Price: Low to High</option>
                            <option value="price_desc" @selected(($filters['sort'] ?? null) === 'price_desc')>Price: High to Low</option>
                            <option value="newest" @selected(($filters['sort'] ?? null) === 'newest')>Newest</option>
                            <option value="name" @selected(($filters['sort'] ?? null) === 'name')>Name</option>
                        </select>
                    </div>
                </div>

                <details class="ys-storefront-advanced-filters" @if (filled($filters['min_price'] ?? null) || filled($filters['max_price'] ?? null)) open @endif>
                    <summary class="ys-storefront-advanced-summary">
                        <span>Advanced filters</span>
                        @if (filled($filters['min_price'] ?? null) || filled($filters['max_price'] ?? null))
                            <span class="ys-storefront-advanced-badge">Price active</span>
                        @endif
                    </summary>

                    <div class="ys-storefront-advanced-panel">
                        <div class="ys-storefront-advanced-grid">
                            <input type="number" name="min_price" value="{{ $filters['min_price'] ?? '' }}" min="0" class="ys-input" placeholder="Min &#8369;">
                            <input type="number" name="max_price" value="{{ $filters['max_price'] ?? '' }}" min="0" class="ys-input" placeholder="Max &#8369;">
                        </div>

                        <div class="ys-storefront-advanced-actions">
                            <button class="ys-button-secondary">Apply filters</button>
                            <a href="{{ route('storefront.shop', request()->except(['min_price', 'max_price', 'page'])) }}" class="ys-link-inline text-sm">
                                Clear price
                            </a>
                        </div>
                    </div>
                </details>
            </form>

            <div class="ys-storefront-chip-row">
                @php
                    $isAll = ! request('category');
                @endphp

                <a href="{{ route('storefront.shop', request()->except('category', 'page')) }}" class="ys-filter-pill {{ $isAll ? 'ys-filter-pill-active' : '' }}">All</a>
                @foreach ($filterCategories as $category)
                    <a
                        href="{{ route('storefront.shop', array_merge(request()->except('page'), ['category' => $category->slug])) }}"
                        class="ys-filter-pill {{ request('category') === $category->slug ? 'ys-filter-pill-active' : '' }}"
                    >
                        {{ $category->name }}
                    </a>
                @endforeach
            </div>

            <div class="ys-storefront-visual-search-rail">
                <div>
                    <p class="text-sm font-semibold text-ys-ivory">Need to match a shoe from a photo?</p>
                    <p class="mt-1 text-xs text-ys-ivory/46">Upload a shoe photo here to find the closest styles from the catalog.</p>
                </div>
                <button
                    type="button"
                    class="ys-button-ghost justify-center text-[0.88rem]"
                    data-inline-visual-search-trigger
                    aria-expanded="false"
                    aria-controls="shop-inline-visual-search"
                >
                    Find similar by image
                </button>
            </div>

            <section
                id="shop-inline-visual-search"
                class="ys-storefront-inline-visual-search hidden"
                data-inline-visual-search
                data-visual-search-endpoint="{{ route('storefront.assistant.visual-search') }}"
                aria-hidden="true"
            >
                <div class="ys-storefront-inline-visual-search-header">
                    <div>
                        <p class="text-sm font-semibold text-ys-ivory">Find similar styles by image</p>
                        <p class="mt-1 text-xs text-ys-ivory/46">Drag a shoe photo here or browse your files to search the current catalog.</p>
                    </div>
                    <button type="button" class="ys-chat-inline-link" data-inline-visual-search-hide>
                        Hide panel
                    </button>
                </div>

                <form class="ys-storefront-inline-visual-search-form" data-inline-visual-search-form novalidate>
                    <input
                        type="file"
                        name="image"
                        accept="image/*,.jpg,.jpeg,.png,.webp,.heic,.heif"
                        class="sr-only"
                        data-inline-visual-search-input
                    >

                    <div
                        class="ys-storefront-inline-dropzone"
                        data-inline-visual-search-dropzone
                        tabindex="0"
                        role="button"
                        aria-label="Upload a shoe photo"
                    >
                        <div class="ys-storefront-inline-dropzone-icon" aria-hidden="true">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                <path d="M12 16V7.5" stroke-linecap="round" />
                                <path d="m8.5 11 3.5-3.5 3.5 3.5" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M5 16.5v.5A2.5 2.5 0 0 0 7.5 19h9a2.5 2.5 0 0 0 2.5-2.5v-.5" stroke-linecap="round" />
                                <rect x="4" y="4" width="16" height="16" rx="4" />
                            </svg>
                        </div>
                        <p class="ys-storefront-inline-dropzone-title">Drag &amp; drop a shoe photo here</p>
                        <p class="ys-storefront-inline-dropzone-copy">or browse files</p>
                        <span class="ys-storefront-inline-dropzone-button">Browse Files</span>
                    </div>

                    <div class="ys-storefront-inline-visual-status hidden" data-inline-visual-search-status>
                        <div class="ys-storefront-inline-visual-status-copy">
                            <p class="ys-storefront-inline-visual-status-name" data-inline-visual-search-file-name></p>
                            <p class="ys-storefront-inline-visual-status-text" data-inline-visual-search-status-text>Ready to search the catalog.</p>
                        </div>
                        <div class="ys-storefront-inline-visual-status-actions">
                            <button type="button" class="ys-chat-inline-link" data-inline-visual-search-reset>
                                Choose another image
                            </button>
                            <button type="button" class="ys-chat-inline-link hidden" data-inline-visual-search-clear>
                                Clear image search
                            </button>
                        </div>
                    </div>
                </form>

                <div class="ys-storefront-inline-visual-empty hidden" data-inline-visual-search-empty aria-live="polite"></div>
            </section>
        </div>

        <div class="mt-8 flex items-center justify-between gap-4 text-sm text-ys-ivory/45" data-storefront-grid-summary>
            <p data-storefront-grid-count>
                Showing {{ $products->firstItem() ?? 0 }}-{{ $products->lastItem() ?? 0 }}
                of {{ $products->total() }} {{ \Illuminate\Support\Str::plural('product', $products->total()) }}
            </p>
            @if ($activeCategory)
                <p class="font-medium text-ys-ivory/55" data-storefront-grid-filter-label>Filtered by {{ $activeCategory->name }}</p>
            @endif
        </div>

        @if ($products->isEmpty())
            <div class="mt-10 rounded-[2rem] border border-dashed border-white/12 bg-white/[0.02] px-8 py-24 text-center" data-storefront-empty-state data-reveal>
                <p class="font-serif text-4xl text-ys-ivory">No products found.</p>
                <p class="mx-auto mt-4 max-w-md text-sm leading-7 text-ys-ivory/48">
                    Try a broader search, switch category filters, or reset the sort to featured.
                </p>
                <a href="{{ route('storefront.shop') }}" class="ys-button-primary mt-8">Reset filters</a>
            </div>
        @else
            <div class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-4" data-storefront-product-grid>
                @foreach ($products as $product)
                    <x-storefront.product-card :product="$product" />
                @endforeach
            </div>

            <div class="mt-10" data-storefront-pagination data-reveal>
                {{ $products->onEachSide(1)->links('vendor.pagination.storefront') }}
            </div>
        @endif
    </section>
@endsection
