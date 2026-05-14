@extends('layouts.storefront', ['title' => "{$product->name} | Ysabelle Retail"])

@inject('media', 'App\\Support\\Storefront\\ProductMediaResolver')

@section('assistant_page_context')
    <script type="application/json" data-assistant-page-context>
        {{ json_encode([
            'current_product' => [
                'id' => $product->id,
                'slug' => $product->slug,
                'name' => $product->name,
                'style_code' => $product->style_code,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}
    </script>
@endsection

@section('content')
    @php
        $imageUrl = $media->imageUrlFor($product);
        $imageAlt = $media->altTextFor($product);
        $availability = $productAvailability ?? [
            'state' => 'out_of_stock',
            'label' => 'Currently Unavailable',
            'available_quantity' => 0,
            'inventory_tracked' => true,
            'color_options' => [],
            'size_options' => [],
        ];
        $colorOptions = collect($availability['color_options'] ?? []);
        $selectedVariantId = (string) old('variant_id', '');
        $selectedOption = collect($availability['variant_options'] ?? [])->first(
            fn (array $option): bool => $selectedVariantId !== '' && (string) ($option['variant_id'] ?? '') === $selectedVariantId
        );
        $selectedColorKey = (string) ($selectedOption['color'] ?? old('selected_color', $availability['default_color'] ?? ''));
        $selectedColor = $colorOptions->firstWhere('color_key', $selectedColorKey) ?? $colorOptions->first();
        $selectedColorKey = (string) ($selectedColor['color_key'] ?? '');
        $sizeOptions = collect($selectedColor['size_options'] ?? []);
        $selectedOption = $sizeOptions->firstWhere('variant_id', (int) ($selectedOption['variant_id'] ?? 0));
        $availabilityCopy = $selectedOption['label'] ?? 'Select a size to view availability.';
        $initialButtonLabel = match (true) {
            ! $selectedOption => 'Select a size',
            ($selectedOption['backorder_available'] ?? false) === true => 'Preorder now',
            ($selectedOption['is_selectable'] ?? false) === true => 'Add to cart',
            default => 'Currently unavailable',
        };
        $initialAvailabilityNote = $selectedOption['label'] ?? 'Select a size to view availability.';
        $trustMarks = collect(($storefrontTrustMarks ?? config('storefront.trust_marks')) ?: [
            [
                'label' => 'Secure Checkout',
                'description' => 'Protected payments and safe transactions.',
            ],
            [
                'label' => 'Premium Quality',
                'description' => 'Carefully selected footwear for everyday performance.',
            ],
            [
                'label' => 'Fast Delivery',
                'description' => 'Reliable shipping for every confirmed order.',
            ],
        ])->filter(fn ($mark) => filled(data_get($mark, 'label')) || filled(data_get($mark, 'description')))->values();
        $productRelatedItems = collect($relatedProducts ?? []);
        $reviewSummary = $productReviewSummary ?? ['average' => null, 'count' => 0, 'breakdown' => collect()];
        $reviewErrors = $errors->review;
        $activeReview = $viewerReview;
        $canManageReview = (bool) ($reviewEligibility['can_review'] ?? false) || $activeReview;
        $reviewAction = $activeReview
            ? route('storefront.catalog.products.reviews.update', [$product, $activeReview])
            : route('storefront.catalog.products.reviews.store', $product);
        $reviewFormRating = old('rating', $activeReview?->rating ?? 0);
        $reviewFormTitle = old('title', $activeReview?->title ?? '');
        $reviewFormBody = old('body', $activeReview?->body ?? '');
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

                <div class="mt-5 flex flex-wrap items-center gap-2">
                    @if ($product->shows_new_badge)
                        <span class="ys-status-pill bg-ys-gold text-ys-ink">New</span>
                    @endif
                    @if ($product->shows_sale_badge)
                        <span class="ys-status-pill bg-[#e44040] text-white">Sale</span>
                    @endif
                </div>

                <div class="mt-5 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-ys-ivory/55">
                    @if ($product->shows_rating_summary)
                        <span class="inline-flex items-center gap-1.5 text-ys-gold">
                            <svg class="h-3.5 w-3.5 fill-current" viewBox="0 0 20 20">
                                <path d="m10 1.7 2.52 5.1 5.63.82-4.08 3.98.96 5.62L10 14.54l-5.03 2.65.96-5.62L1.85 7.6l5.63-.82L10 1.7Z" />
                            </svg>
                            {{ number_format((float) $reviewSummary['average'], 1) }}
                        </span>
                        <span>({{ $reviewSummary['count'] }} reviews)</span>
                    @else
                        <span class="font-medium uppercase tracking-[0.18em] text-ys-ivory/38">No reviews yet</span>
                    @endif
                    <span>&middot;</span>
                    <span data-product-availability-label>{{ $availabilityCopy }}</span>
                </div>

                <div class="mt-6 flex items-center gap-3">
                    <p class="text-4xl font-semibold text-ys-gold">&#8369;{{ number_format((float) $product->base_price, 0) }}</p>
                    @if ($product->shows_sale_badge)
                        <p class="text-xl text-ys-ivory/28 line-through">&#8369;{{ number_format((float) $product->compare_at_price, 0) }}</p>
                    @endif
                </div>

                <p class="mt-8 text-base leading-8 text-ys-ivory/58">{{ $product->description }}</p>

                <form action="{{ route('storefront.cart.store') }}" method="POST" class="mt-10 space-y-8" data-product-form>
                    @csrf
                    @if ($errors->has('variant_id') || $errors->has('quantity') || $errors->has('inventory'))
                        <div class="rounded-[1.4rem] border border-[#7c2727] bg-[#361010] px-5 py-4 text-sm text-[#ffd8d8]">
                            <p class="font-semibold">We couldn't add that variant yet.</p>
                            <ul class="mt-2 space-y-1 text-[#ffdddd]/85">
                                @foreach (['variant_id', 'quantity', 'inventory'] as $field)
                                    @foreach ($errors->get($field) as $message)
                                        <li>{{ $message }}</li>
                                    @endforeach
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <script type="application/json" data-product-availability>
                        {!! json_encode([
                            'color_options' => $colorOptions->values()->all(),
                            'selected_color' => $selectedColorKey,
                            'selected_variant_id' => $selectedOption['variant_id'] ?? null,
                            'default_availability_label' => 'Select a size to view availability.',
                            'default_add_to_cart_label' => 'Select a size',
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
                    </script>

                    <input type="hidden" name="variant_id" value="{{ $selectedOption['variant_id'] ?? old('variant_id') }}">
                    <input type="hidden" name="selected_color" value="{{ $selectedColorKey }}" data-selected-color-input>
                    <input type="hidden" name="quantity" value="{{ old('quantity', 1) }}" data-quantity-input>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.35em] text-ys-ivory/45">Select Color</p>
                        <div class="mt-4 flex flex-wrap gap-3" data-color-options>
                            @foreach ($colorOptions as $option)
                                @php
                                    $isSelectedColor = $selectedColorKey !== '' && (string) ($option['color_key'] ?? '') === $selectedColorKey;
                                @endphp
                                <button
                                    type="button"
                                    class="ys-size-option {{ $isSelectedColor ? 'ys-size-option-active' : '' }}"
                                    data-color-option
                                    data-color-key="{{ $option['color_key'] }}"
                                    data-color-label="{{ $option['color_label'] }}"
                                    aria-pressed="{{ $isSelectedColor ? 'true' : 'false' }}"
                                >
                                    {{ $option['color_label'] }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.35em] text-ys-ivory/45">Select Size (US)</p>
                        <div class="mt-4 flex flex-wrap gap-3" data-size-options>
                            @foreach ($sizeOptions as $option)
                                @php
                                    $selected = $selectedOption && (int) $selectedOption['variant_id'] === (int) $option['variant_id'];
                                    $isSelectable = (bool) ($option['is_selectable'] ?? false);
                                @endphp
                                <button
                                    type="button"
                                    class="ys-size-option {{ $selected ? 'ys-size-option-active' : '' }} {{ $isSelectable ? '' : 'ys-size-option-unavailable' }}"
                                    data-variant-option
                                    data-variant-id="{{ $option['variant_id'] }}"
                                    data-variant-size="{{ $option['size'] }}"
                                    data-variant-color="{{ $option['color'] }}"
                                    data-variant-state="{{ $option['state'] }}"
                                    data-variant-selectable="{{ $isSelectable ? '1' : '0' }}"
                                    data-variant-label="{{ $option['label'] }}"
                                    data-variant-backorder="{{ ($option['backorder_available'] ?? false) ? '1' : '0' }}"
                                    @disabled(! $isSelectable)
                                    aria-disabled="{{ $isSelectable ? 'false' : 'true' }}"
                                >
                                    {{ $option['size'] }}
                                </button>
                            @endforeach
                        </div>
                        <p class="mt-3 text-sm text-ys-ivory/48" data-selected-availability>{{ $initialAvailabilityNote }}</p>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.35em] text-ys-ivory/45">Quantity</p>
                        <div class="mt-4 inline-flex items-center rounded-2xl border border-white/10 bg-white/[0.02] p-1">
                            <button type="button" class="ys-quantity-button" data-quantity-step="-1">&minus;</button>
                            <span class="inline-flex min-w-12 items-center justify-center text-sm font-semibold text-ys-ivory" data-quantity-display>{{ old('quantity', 1) }}</span>
                            <button type="button" class="ys-quantity-button" data-quantity-step="1">+</button>
                        </div>
                    </div>

                    <button
                        type="submit"
                        class="ys-button-primary w-full justify-center text-base disabled:cursor-not-allowed disabled:opacity-60"
                        data-add-to-cart-button
                        @disabled(! $selectedOption || ! ($selectedOption['is_selectable'] ?? false))
                    >
                        {{ $initialButtonLabel }}
                    </button>
                </form>

                <div class="mt-8 grid gap-4 border-t border-white/7 pt-7 sm:grid-cols-3">
                    @foreach ($trustMarks as $mark)
                        <div class="text-sm">
                            <p class="font-semibold text-ys-ivory">{{ data_get($mark, 'label', 'Store Promise') }}</p>
                            <p class="mt-1 text-xs text-ys-ivory/45">{{ data_get($mark, 'description', 'Premium service at every step.') }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section id="reviews" class="ys-container pb-18">
        <div class="grid gap-6 xl:grid-cols-[0.82fr_1.18fr]">
            <div class="space-y-6" data-reveal>
                <div class="rounded-[2rem] border border-white/8 bg-white/[0.02] p-7 shadow-[0_18px_60px_rgba(0,0,0,0.3)]">
                    <p class="text-xs font-semibold uppercase tracking-[0.35em] text-ys-gold/90">Ratings & Reviews</p>

                    @if ($product->shows_rating_summary)
                        <div class="mt-4 flex items-end gap-4">
                            <p class="font-serif text-6xl leading-none text-ys-ivory">{{ number_format((float) $reviewSummary['average'], 1) }}</p>
                            <div class="pb-1">
                                <div class="flex items-center gap-1.5 text-ys-gold">
                                    @for ($star = 1; $star <= 5; $star++)
                                        <svg class="h-4 w-4 fill-current {{ $star <= round((float) $reviewSummary['average']) ? '' : 'text-ys-ivory/18' }}" viewBox="0 0 20 20">
                                            <path d="m10 1.7 2.52 5.1 5.63.82-4.08 3.98.96 5.62L10 14.54l-5.03 2.65.96-5.62L1.85 7.6l5.63-.82L10 1.7Z" />
                                        </svg>
                                    @endfor
                                </div>
                                <p class="mt-2 text-sm text-ys-ivory/52">Based on {{ $reviewSummary['count'] }} verified customer {{ \Illuminate\Support\Str::plural('review', $reviewSummary['count']) }}.</p>
                            </div>
                        </div>

                        <div class="mt-6 space-y-3">
                            @foreach ($reviewSummary['breakdown'] as $breakdownRow)
                                <div class="flex items-center gap-3 text-sm text-ys-ivory/58">
                                    <span class="w-7 text-right">{{ $breakdownRow['rating'] }}&#9733;</span>
                                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-white/6">
                                        <div class="h-full rounded-full bg-ys-gold/85" style="width: {{ number_format((float) $breakdownRow['share'], 2, '.', '') }}%"></div>
                                    </div>
                                    <span class="w-8 text-right text-ys-ivory/42">{{ $breakdownRow['count'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="mt-5 rounded-[1.4rem] border border-dashed border-white/12 bg-white/[0.02] px-5 py-6 text-sm text-ys-ivory/48">
                            No reviews yet. Verified customers will see their review option here after a completed paid order.
                        </div>
                    @endif
                </div>

                <div class="rounded-[2rem] border border-white/8 bg-black/25 p-7 shadow-[0_18px_60px_rgba(0,0,0,0.28)]" data-reveal>
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.35em] text-ys-gold/90">{{ $activeReview ? 'Update Your Review' : 'Write a Review' }}</p>
                            <h2 class="mt-3 font-serif text-3xl text-ys-ivory">Real customer feedback only</h2>
                        </div>
                        @if ($activeReview?->is_verified_purchase)
                            <span class="rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3 py-1 text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-emerald-200">Verified purchase</span>
                        @endif
                    </div>

                    @if ($reviewErrors->any())
                        <div class="mt-5 rounded-2xl border border-rose-400/25 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                            <p class="font-semibold">We couldn't save your review yet.</p>
                            <ul class="mt-2 space-y-1">
                                @foreach ($reviewErrors->all() as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @auth
                        @if ($canManageReview)
                            <p class="mt-5 text-sm leading-7 text-ys-ivory/52">{{ $reviewEligibility['reason'] ?? 'Share a review based on your purchase experience.' }}</p>

                            <form action="{{ $reviewAction }}" method="POST" class="mt-6 space-y-5" data-review-form>
                                @csrf
                                @if ($activeReview)
                                    @method('PUT')
                                @endif

                                <div data-review-rating-group data-current-rating="{{ $reviewFormRating }}">
                                    <input type="hidden" name="rating" value="{{ $reviewFormRating }}">
                                    <p class="text-xs font-semibold uppercase tracking-[0.35em] text-ys-ivory/45">Rating</p>
                                    <div class="mt-3 flex items-center gap-2">
                                        @for ($star = 1; $star <= 5; $star++)
                                            <button
                                                type="button"
                                                class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-white/12 text-ys-ivory/35 transition hover:border-ys-gold/80 hover:text-ys-gold"
                                                data-review-star="{{ $star }}"
                                                aria-label="Rate {{ $star }} out of 5"
                                                aria-pressed="false"
                                            >
                                                <svg class="h-5 w-5 fill-current" viewBox="0 0 20 20" aria-hidden="true">
                                                    <path d="m10 1.7 2.52 5.1 5.63.82-4.08 3.98.96 5.62L10 14.54l-5.03 2.65.96-5.62L1.85 7.6l5.63-.82L10 1.7Z" />
                                                </svg>
                                            </button>
                                        @endfor
                                    </div>
                                </div>

                                <div>
                                    <label for="review-title" class="text-xs font-semibold uppercase tracking-[0.35em] text-ys-ivory/45">Title</label>
                                    <input id="review-title" type="text" name="title" value="{{ $reviewFormTitle }}" maxlength="120" class="ys-input mt-3" placeholder="Optional headline">
                                </div>

                                <div>
                                    <label for="review-body" class="text-xs font-semibold uppercase tracking-[0.35em] text-ys-ivory/45">Your review</label>
                                    <textarea id="review-body" name="body" rows="6" class="ys-input mt-3 min-h-36 resize-y" placeholder="Share your fit, comfort, and overall experience.">{{ $reviewFormBody }}</textarea>
                                </div>

                                <div class="flex flex-wrap items-center gap-3">
                                    <button type="submit" class="ys-button-primary" data-review-submit data-loading-label="{{ $activeReview ? 'Updating review...' : 'Publishing review...' }}">
                                        {{ $activeReview ? 'Update review' : 'Publish review' }}
                                    </button>
                                </div>
                            </form>

                            @if ($activeReview)
                                <form action="{{ route('storefront.catalog.products.reviews.destroy', [$product, $activeReview]) }}" method="POST" class="mt-3" data-review-delete-form>
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="ys-button-secondary" data-review-delete-submit data-loading-label="Removing review...">
                                        Delete review
                                    </button>
                                </form>
                            @endif
                        @else
                            <p class="mt-5 text-sm leading-7 text-ys-ivory/52">{{ $reviewEligibility['reason'] ?? 'Only verified customers can review this product.' }}</p>
                        @endif
                    @else
                        <p class="mt-5 text-sm leading-7 text-ys-ivory/52">
                            <a href="{{ route('login') }}" class="text-ys-gold underline-offset-4 hover:underline">Sign in</a>
                            with your customer account after purchase to leave a verified review.
                        </p>
                    @endauth
                </div>
            </div>

            <div class="space-y-5" data-reveal>
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.35em] text-ys-gold/90">Community Notes</p>
                        <h2 class="mt-3 font-serif text-3xl text-ys-ivory">Customer reviews</h2>
                    </div>
                    @if ($product->shows_rating_summary)
                        <p class="text-sm text-ys-ivory/48">{{ $reviewSummary['count'] }} {{ \Illuminate\Support\Str::plural('review', $reviewSummary['count']) }}</p>
                    @endif
                </div>

                @forelse ($productReviews as $review)
                    <article class="rounded-[1.7rem] border border-white/8 bg-white/[0.02] p-6 shadow-[0_16px_55px_rgba(0,0,0,0.22)]">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <h3 class="text-lg font-semibold text-ys-ivory">{{ $review->user?->name ?? 'Verified customer' }}</h3>
                                    @if ($review->is_verified_purchase)
                                        <span class="rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3 py-1 text-[0.64rem] font-semibold uppercase tracking-[0.22em] text-emerald-200">Verified purchase</span>
                                    @endif
                                </div>
                                <div class="mt-3 flex items-center gap-1.5 text-ys-gold">
                                    @for ($star = 1; $star <= 5; $star++)
                                        <svg class="h-4 w-4 fill-current {{ $star <= $review->rating ? '' : 'text-ys-ivory/18' }}" viewBox="0 0 20 20">
                                            <path d="m10 1.7 2.52 5.1 5.63.82-4.08 3.98.96 5.62L10 14.54l-5.03 2.65.96-5.62L1.85 7.6l5.63-.82L10 1.7Z" />
                                        </svg>
                                    @endfor
                                </div>
                            </div>
                            <p class="text-sm text-ys-ivory/42">{{ \App\Support\BusinessTime::format($review->created_at, 'F j, Y') }}</p>
                        </div>

                        @if (filled($review->title))
                            <h4 class="mt-5 text-base font-semibold text-ys-ivory">{{ $review->title }}</h4>
                        @endif

                        <div class="mt-3 text-sm leading-7 text-ys-ivory/58">{!! nl2br(e($review->body)) !!}</div>
                    </article>
                @empty
                    <div class="rounded-[1.7rem] border border-dashed border-white/12 bg-white/[0.02] px-8 py-16 text-center">
                        <p class="font-serif text-3xl text-ys-ivory">No reviews yet</p>
                        <p class="mx-auto mt-4 max-w-lg text-sm leading-7 text-ys-ivory/48">
                            Once verified customers complete a paid order for this product, their reviews will appear here.
                        </p>
                    </div>
                @endforelse

                @if ($productReviews->hasPages())
                    <div class="pt-4">
                        {{ $productReviews->fragment('reviews')->onEachSide(1)->links('vendor.pagination.storefront') }}
                    </div>
                @endif
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
            @foreach ($productRelatedItems as $relatedProduct)
                <x-storefront.product-card :product="$relatedProduct" />
            @endforeach
        </div>
    </section>
@endsection
