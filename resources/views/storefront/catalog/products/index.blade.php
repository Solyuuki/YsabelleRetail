@extends('layouts.storefront', ['title' => 'Shop All Shoes | Ysabelle Retail'])

@section('content')
    <section class="ys-container pb-18 pt-10 lg:pt-14">
        <x-storefront.section-heading
            eyebrow="The Collection"
            title="Shop all shoes"
            description="Engineered for performance. Designed for legacy."
        />

        <div class="mt-10 rounded-[1.8rem] border border-white/7 bg-ys-panel/70 p-4 md:p-5" data-reveal>
            <form method="GET" action="{{ route('storefront.shop') }}" class="grid gap-4 lg:grid-cols-[1fr_210px]">
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-4 inline-flex items-center text-ys-ivory/28">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                            <circle cx="11" cy="11" r="6.5" />
                            <path d="m16 16 4.5 4.5" stroke-linecap="round" />
                        </svg>
                    </span>
                    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="ys-input pl-11" placeholder="Search shoes, brands...">
                </div>

                <div class="flex gap-3">
                    <select name="sort" class="ys-select flex-1" data-auto-submit>
                        <option value="featured" @selected(($filters['sort'] ?? 'featured') === 'featured')>Featured</option>
                        <option value="price_asc" @selected(($filters['sort'] ?? null) === 'price_asc')>Price: Low to High</option>
                        <option value="price_desc" @selected(($filters['sort'] ?? null) === 'price_desc')>Price: High to Low</option>
                        <option value="newest" @selected(($filters['sort'] ?? null) === 'newest')>Newest</option>
                        <option value="name" @selected(($filters['sort'] ?? null) === 'name')>Name</option>
                    </select>
                    <button class="ys-button-secondary hidden md:inline-flex">Apply</button>
                </div>
            </form>

            <div class="mt-5 flex flex-wrap gap-3">
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
        </div>

        <div class="mt-8 flex items-center justify-between gap-4 text-sm text-ys-ivory/45">
            <p>{{ $products->total() }} {{ \Illuminate\Support\Str::plural('piece', $products->total()) }}</p>
            @if (request('category'))
                <p class="font-medium text-ys-ivory/55">Filtered by {{ ucfirst(request('category')) }}</p>
            @endif
        </div>

        @if ($products->isEmpty())
            <div class="mt-10 rounded-[2rem] border border-dashed border-white/12 bg-white/[0.02] px-8 py-24 text-center" data-reveal>
                <p class="font-serif text-4xl text-ys-ivory">No shoes matched your filters.</p>
                <p class="mx-auto mt-4 max-w-md text-sm leading-7 text-ys-ivory/48">
                    Try a broader search, switch category filters, or reset the sort to featured.
                </p>
                <a href="{{ route('storefront.shop') }}" class="ys-button-primary mt-8">Reset filters</a>
            </div>
        @else
            <div class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($products as $product)
                    <x-storefront.product-card :product="$product" />
                @endforeach
            </div>
        @endif
    </section>
@endsection
