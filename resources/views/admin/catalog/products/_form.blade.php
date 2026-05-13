@php
    $mediaPath = app(\App\Support\Storefront\ProductMediaPath::class);
    $mediaResolver = app(\App\Support\Storefront\ProductMediaResolver::class);
    $selectedCategoryId = old('category_id', $product->category_id);
    $selectedCategory = $categories->firstWhere('id', (int) $selectedCategoryId);
    $newBadgeWindowDays = (int) config('storefront.catalog.new_badge_window_days', 60);
    $visibilityDiagnostics = $visibilityDiagnostics ?? null;
    $deletionAssessment = $deletionAssessment ?? null;
    $hasOldInput = session()->hasOldInput();
    $variantDefaults = old('variants', $product->variants->map(function ($variant) {
        return [
            'id' => $variant->id,
            'name' => $variant->name,
            'sku' => $variant->sku,
            'barcode' => $variant->barcode,
            'size' => $variant->option_values['size'] ?? null,
            'color' => $variant->option_values['color'] ?? null,
            'price' => $variant->price,
            'compare_at_price' => $variant->compare_at_price,
            'cost_price' => $variant->cost_price,
            'supplier_name' => $variant->supplier_name,
            'weight_grams' => $variant->weight_grams,
            'status' => $variant->status,
            'quantity_on_hand' => $variant->inventoryItem?->quantity_on_hand ?? 0,
            'reorder_level' => $variant->inventoryItem?->reorder_level ?? 0,
            'allow_backorder' => $variant->inventoryItem?->allow_backorder ?? false,
        ];
    })->all());

    if ($variantDefaults === []) {
        $variantDefaults = [[
            'id' => null,
            'name' => 'Default Variant',
            'sku' => '',
            'barcode' => '',
            'size' => '',
            'color' => '',
            'price' => '',
            'compare_at_price' => '',
            'cost_price' => '',
            'supplier_name' => '',
            'weight_grams' => '',
            'status' => 'active',
            'quantity_on_hand' => 0,
            'reorder_level' => 4,
            'allow_backorder' => false,
        ]];
    }

    $toMoney = function (mixed $value): ?float {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    };

    $oldPrimaryImageValue = old('primary_image_url');
    $initialImagePathValue = $hasOldInput
        ? trim((string) ($oldPrimaryImageValue ?? ''))
        : trim((string) ($product->primary_image_url ?? ''));
    $resolvedPreviewUrl = old('remove_primary_image', '0') === '1'
        ? null
        : ($hasOldInput
            ? $mediaPath->toUrl($oldPrimaryImageValue)
            : $mediaResolver->imageUrlFor($product));
    $initialImageMode = match (true) {
        $resolvedPreviewUrl === null => 'none',
        $initialImagePathValue !== '' => 'path',
        default => 'fallback',
    };
    $initialImageMessage = $resolvedPreviewUrl
        ? 'Current product image is ready. Upload a replacement any time.'
        : 'No product image yet. Upload one now or keep the branded placeholder until you are ready.';
    $previewTitle = old('name', $product->name ?: 'Untitled Product');
    $previewCategory = $selectedCategory?->name ?? $product->category?->name ?? 'Collection';
    $previewDescription = old('short_description', $product->short_description ?: 'Add a short description to help your team understand how this product appears on the storefront.');
    $previewTrackInventory = filter_var(old('track_inventory', $product->track_inventory ?? true), FILTER_VALIDATE_BOOL);
    $previewStatus = old('status', $product->status ?: 'active');
    $previewForceNew = filter_var(old('force_new_badge', $product->force_new_badge ?? false), FILTER_VALIDATE_BOOL);
    $previewFeatured = filter_var(old('is_featured', $product->is_featured ?? false), FILTER_VALIDATE_BOOL);
    $previewActiveVariants = collect($variantDefaults)
        ->filter(fn (array $variant): bool => ($variant['status'] ?? 'active') === 'active')
        ->values();
    $previewPricedVariants = $previewActiveVariants
        ->map(function (array $variant) use ($toMoney): array {
            return [
                'price' => $toMoney($variant['price'] ?? null),
                'compare_at_price' => $toMoney($variant['compare_at_price'] ?? null),
            ];
        })
        ->filter(fn (array $variant): bool => $variant['price'] !== null)
        ->sortBy('price')
        ->values();
    $previewLowestPricedVariant = $previewPricedVariants->first();
    $previewBasePrice = $previewLowestPricedVariant['price'] ?? null;
    $previewSaleVariant = $previewPricedVariants
        ->first(fn (array $variant): bool => ($variant['compare_at_price'] ?? null) !== null && $variant['compare_at_price'] > $variant['price']);
    $previewComparePrice = ($previewLowestPricedVariant['compare_at_price'] ?? null) !== null
        && $previewLowestPricedVariant['compare_at_price'] > ($previewLowestPricedVariant['price'] ?? 0)
            ? $previewLowestPricedVariant['compare_at_price']
            : ($previewSaleVariant['compare_at_price'] ?? null);
    $previewHasSale = $previewSaleVariant !== null;
    $previewQuantity = $previewTrackInventory
        ? (int) $previewActiveVariants->sum(fn (array $variant): int => (int) ($variant['quantity_on_hand'] ?? 0))
        : null;
    $previewReorderLevel = $previewTrackInventory
        ? (int) $previewActiveVariants->sum(fn (array $variant): int => (int) ($variant['reorder_level'] ?? 0))
        : null;
    $previewBackorder = $previewTrackInventory
        ? $previewActiveVariants->contains(fn (array $variant): bool => filter_var($variant['allow_backorder'] ?? false, FILTER_VALIDATE_BOOL))
        : false;
    $previewIsNew = $previewForceNew
        || ! $product->exists
        || $product->created_at?->greaterThanOrEqualTo(now()->subDays($newBadgeWindowDays));
    $previewAvailabilityState = match (true) {
        $previewStatus !== 'active' => 'inactive',
        ! $previewTrackInventory => 'in_stock',
        $previewQuantity > 0 && $previewReorderLevel > 0 && $previewQuantity <= $previewReorderLevel => 'low_stock',
        $previewQuantity > 0 => 'in_stock',
        $previewBackorder => 'available_for_backorder',
        default => 'sold_out',
    };
    $previewAvailabilityLabel = match ($previewAvailabilityState) {
        'inactive' => 'Inactive',
        'low_stock' => 'Low Stock',
        'available_for_backorder' => 'Available for Backorder',
        'in_stock' => 'In Stock',
        default => 'Sold Out',
    };
    $previewAvailabilityClasses = match ($previewAvailabilityState) {
        'in_stock' => 'bg-[#11311f] text-[#9fe1b1]',
        'low_stock' => 'bg-[#38260c] text-[#f0c36f]',
        'available_for_backorder' => 'bg-[#112a3f] text-[#9fd4ff]',
        'inactive' => 'bg-[#2f2b36] text-[#ddd3f0]',
        default => 'bg-[#411415] text-[#ffb0b0]',
    };
    $advancedHasErrors = $errors->hasAny([
        'slug',
        'style_code',
        'featured_rank',
        'primary_image_url',
        'image_alt',
    ]);
    $variantErrorIndexes = collect(array_keys($errors->messages()))
        ->map(function (string $key): ?string {
            if (preg_match('/^variants\.(\d+)\./', $key, $matches) !== 1) {
                return null;
            }

            return $matches[1];
        })
        ->filter()
        ->unique()
        ->values();
    $previewCurrency = '&#8369;';
@endphp

@if ($errors->any())
    <div class="ys-admin-form-error">
        <p class="font-semibold">Please review the product builder before saving.</p>
        <ul class="mt-2 space-y-1 text-sm">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-6" data-admin-form>
    @csrf
    @isset($method)
        @method($method)
    @endisset

    <div
        class="ys-admin-product-builder"
        data-product-builder
        data-origin="{{ url('/') }}"
        data-initial-image-url="{{ $resolvedPreviewUrl ?? '' }}"
        data-initial-image-message="{{ $initialImageMessage }}"
        data-initial-image-alt="{{ old('image_alt', $product->image_alt ?: $previewTitle) }}"
        data-initial-image-mode="{{ $initialImageMode }}"
        data-initial-image-path-value="{{ $initialImagePathValue }}"
        data-variant-per-page="4"
        data-initial-error-variant-indexes="{{ $variantErrorIndexes->implode(',') }}"
        data-new-window-days="{{ $newBadgeWindowDays }}"
        data-initial-is-recent="{{ ! $product->exists || $product->created_at?->greaterThanOrEqualTo(now()->subDays($newBadgeWindowDays)) ? '1' : '0' }}"
        data-label-in-stock="In Stock"
        data-label-low-stock="Low Stock"
        data-label-backorder="Available for Backorder"
        data-label-sold-out="Sold Out"
        data-label-inactive="Inactive"
    >
        <div class="ys-admin-product-builder-main space-y-6">
            <section class="ys-admin-panel ys-admin-builder-section" data-admin-panel>
                <div class="ys-admin-panel-heading">
                    <div>
                        <h2 class="ys-admin-panel-title">Basic Product Info</h2>
                        <p class="ys-admin-subtle">Start with the information your team recognizes instantly on the sales floor and on the storefront.</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 lg:grid-cols-2">
                    <label class="ys-admin-field lg:col-span-2">
                        <span class="ys-admin-label">Product Name</span>
                        <input type="text" name="name" value="{{ old('name', $product->name) }}" class="ys-admin-input" placeholder="Example: Aurum Runner" data-preview-name>
                        <span class="ys-admin-field-help">Use the shopper-facing name that should appear across the storefront, chatbot, and reports.</span>
                    </label>

                    <label class="ys-admin-field">
                        <span class="ys-admin-label">Category</span>
                        <select name="category_id" class="ys-admin-select" data-preview-category>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected($selectedCategoryId == $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="ys-admin-field">
                        <span class="ys-admin-label">Status</span>
                        <select name="status" class="ys-admin-select" data-preview-status>
                            @foreach (['active' => 'Active', 'draft' => 'Draft', 'archived' => 'Archived'] as $statusValue => $statusLabel)
                                <option value="{{ $statusValue }}" @selected(old('status', $product->status) === $statusValue)>{{ $statusLabel }}</option>
                            @endforeach
                        </select>
                        <span class="ys-admin-field-help">Draft keeps the product hidden from shoppers. Archived keeps its history without deleting records.</span>
                    </label>

                    <label class="ys-admin-field">
                        <span class="ys-admin-label">Short Description</span>
                        <textarea name="short_description" class="ys-admin-textarea" rows="4" data-preview-short-description>{{ old('short_description', $product->short_description) }}</textarea>
                        <span class="ys-admin-field-help">A compact summary for merchandisers, storefront highlights, and quick product context.</span>
                    </label>

                    <label class="ys-admin-field">
                        <span class="ys-admin-label">Description</span>
                        <textarea name="description" class="ys-admin-textarea" rows="4">{{ old('description', $product->description) }}</textarea>
                        <span class="ys-admin-field-help">Use this for the full story, key materials, comfort notes, or any shopper-facing detail.</span>
                    </label>
                </div>
            </section>

            <section class="ys-admin-panel ys-admin-builder-section" data-admin-panel>
                <div class="ys-admin-panel-heading">
                    <div>
                        <h2 class="ys-admin-panel-title">Product Images</h2>
                        <p class="ys-admin-subtle">Upload once, preview instantly, and let the system handle storage paths for you.</p>
                    </div>
                </div>

                <input type="hidden" name="remove_primary_image" value="{{ old('remove_primary_image', '0') }}" data-product-image-remove-flag>

                <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1.1fr)_minmax(18rem,0.9fr)]" data-product-image-field>
                    <div>
                        <button type="button" class="ys-admin-image-dropzone {{ $resolvedPreviewUrl ? 'has-image' : '' }}" data-product-image-dropzone>
                            <img
                                src="{{ $resolvedPreviewUrl ?? '' }}"
                                alt="{{ old('image_alt', $product->image_alt ?: $previewTitle) }}"
                                class="{{ $resolvedPreviewUrl ? '' : 'hidden' }} ys-admin-image-dropzone-image"
                                data-product-image-preview-image
                            >
                            <div class="{{ $resolvedPreviewUrl ? 'hidden' : '' }} ys-admin-image-dropzone-placeholder" data-product-image-placeholder>
                                <img src="{{ asset('brand/yr-logo-full-transparent.png') }}" alt="Ysabelle Retail" class="ys-admin-image-dropzone-logo">
                                <p class="ys-admin-image-dropzone-title">Drop a product image here</p>
                                <p class="ys-admin-image-dropzone-copy">JPG, JPEG, PNG, or WEBP up to 5 MB. This becomes the primary storefront image automatically.</p>
                            </div>
                        </button>
                    </div>

                    <div class="space-y-4">
                        <div class="ys-admin-detail-panel">
                            <p class="ys-admin-detail-kicker">Primary image workflow</p>
                            <h3 class="ys-admin-detail-heading">Retailer-friendly upload</h3>
                            <p class="ys-admin-detail-copy" data-product-image-preview-status>{{ $initialImageMessage }}</p>
                        </div>

                        <input
                            type="file"
                            name="primary_image_upload"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                            class="hidden"
                            data-product-image-upload
                        >

                        <div class="ys-admin-inline-actions">
                            <button type="button" class="ys-admin-button-primary" data-product-image-browse>
                                {{ $resolvedPreviewUrl ? 'Replace image' : 'Upload image' }}
                            </button>
                            <button type="button" class="ys-admin-button-secondary {{ $resolvedPreviewUrl ? '' : 'hidden' }}" data-product-image-remove>
                                Remove image
                            </button>
                        </div>

                        <div class="ys-admin-empty-state is-compact">
                            <div class="ys-admin-empty-state-icon">
                                <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5" aria-hidden="true">
                                    <path d="M4 6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v11A2.5 2.5 0 0 1 17.5 20h-11A2.5 2.5 0 0 1 4 17.5v-11Z" stroke="currentColor" stroke-width="1.5"/>
                                    <path d="m8 15 2.6-2.6a1 1 0 0 1 1.4 0L16 16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                    <circle cx="9" cy="9" r="1.25" fill="currentColor"/>
                                </svg>
                            </div>
                            <div>
                                <p class="ys-admin-empty-state-title">What happens after upload?</p>
                                <p class="ys-admin-empty-state-copy">The system stores the file on Laravel's public disk, updates the existing product image field automatically, and keeps storefront and image-search compatibility intact.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="ys-admin-panel ys-admin-builder-section" data-admin-panel>
                <div class="ys-admin-panel-heading">
                    <div>
                        <h2 class="ys-admin-panel-title">Pricing &amp; Badges</h2>
                        <p class="ys-admin-subtle">Pricing comes from the variant cards below. This summary shows how shoppers will read your price and badges.</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
                    <div class="ys-admin-pricing-preview" data-pricing-preview>
                        <div>
                            <p class="ys-admin-detail-kicker">Storefront pricing</p>
                            <div class="mt-3 flex flex-wrap items-end gap-3">
                                <p class="ys-admin-pricing-preview-price" data-preview-price>{!! $previewBasePrice !== null ? $previewCurrency.number_format($previewBasePrice, 0) : 'Set price' !!}</p>
                                <p class="ys-admin-pricing-preview-compare {{ $previewHasSale ? '' : 'hidden' }}" data-preview-compare-price>{!! $previewComparePrice !== null ? $previewCurrency.number_format($previewComparePrice, 0) : '' !!}</p>
                            </div>
                            <p class="mt-3 text-sm text-ys-ivory/50" data-preview-price-note>
                                Lowest active selling price becomes the storefront price. If Original Price is higher than Selling Price, the SALE badge appears automatically.
                            </p>
                        </div>

                        <div class="ys-admin-pricing-preview-badges">
                            <span class="ys-status-pill bg-ys-gold text-ys-ink {{ $previewIsNew ? '' : 'hidden' }}" data-preview-badge-new>New</span>
                            <span class="ys-status-pill bg-[#e44040] text-white {{ $previewHasSale ? '' : 'hidden' }}" data-preview-badge-sale>Sale</span>
                            <span class="ys-status-pill bg-white/10 text-ys-ivory {{ $previewFeatured ? '' : 'hidden' }}" data-preview-badge-featured>Featured</span>
                        </div>
                    </div>

                    <div class="ys-admin-empty-state">
                        <div class="ys-admin-empty-state-icon">
                            <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5" aria-hidden="true">
                                <path d="M12 3v18M3 12h18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <div>
                            <p class="ys-admin-empty-state-title">Badge logic at a glance</p>
                            <p class="ys-admin-empty-state-copy">SALE is automatic from variant pricing. NEW appears for recent products and can also be manually kept on. FEATURED is controlled by your merchandising toggle below.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="ys-admin-panel ys-admin-builder-section" data-admin-panel>
                <div class="ys-admin-panel-heading">
                    <div>
                        <h2 class="ys-admin-panel-title">Variants &amp; Stock</h2>
                        <p class="ys-admin-subtle">Manage sizes, colors, pricing, and inventory in a way that mirrors how your team thinks about stock.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="ys-admin-button-primary" data-variant-add>
                            Add size
                        </button>
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap items-center gap-4 rounded-[1.1rem] border border-white/7 bg-white/[0.025] px-4 py-3">
                    <label class="inline-flex items-center gap-3 text-sm text-ys-ivory/72">
                        <input type="hidden" name="track_inventory" value="0">
                        <input type="checkbox" name="track_inventory" value="1" @checked(old('track_inventory', $product->track_inventory ?? true)) data-preview-track-inventory>
                        Track inventory for this product
                    </label>
                    <p class="text-sm text-ys-ivory/46">Saving stock changes here still records audited inventory movements instead of silently overwriting stock history.</p>
                </div>

                <div class="ys-admin-variant-manager mt-5">
                    <div>
                        <p class="ys-admin-detail-kicker">Variant manager</p>
                        <h3 class="ys-admin-detail-heading"><span data-variant-count>{{ count($variantDefaults) }}</span> variants</h3>
                        <p class="ys-admin-detail-copy">Only a few variant cards are shown at once, but every variant stays in the form and submits normally.</p>
                    </div>

                    <div class="ys-admin-variant-manager-actions">
                        <label class="ys-admin-field ys-admin-variant-search">
                            <span class="ys-admin-label">Find variant</span>
                            <input type="search" class="ys-admin-input" placeholder="Search size, color, or SKU" data-variant-search>
                        </label>

                        <div class="ys-admin-inline-actions">
                            <button type="button" class="ys-admin-button-secondary" data-variant-prev>Previous</button>
                            <span class="ys-admin-variant-page-label" data-variant-page-label>Page 1 of 1</span>
                            <button type="button" class="ys-admin-button-secondary" data-variant-next>Next</button>
                        </div>
                    </div>
                </div>

                <div id="variant-list" class="mt-5 space-y-4" data-variant-list>
                    @foreach ($variantDefaults as $index => $variant)
                        @include('admin.catalog.products._variant-row', ['index' => $index, 'variant' => $variant])
                    @endforeach
                </div>
            </section>

            <section class="ys-admin-panel ys-admin-builder-section" data-admin-panel>
                <div class="ys-admin-panel-heading">
                    <div>
                        <h2 class="ys-admin-panel-title">Badges &amp; Visibility</h2>
                        <p class="ys-admin-subtle">Make badge behavior obvious for your team before the product ever reaches the storefront.</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 lg:grid-cols-3">
                    <label class="ys-admin-toggle-card">
                        <div>
                            <p class="ys-admin-toggle-title">Featured Product</p>
                            <p class="ys-admin-toggle-copy">Add this item to the featured merchandising pool used across the storefront and discovery experiences.</p>
                        </div>
                        <div class="ys-admin-toggle-control">
                            <input type="hidden" name="is_featured" value="0">
                            <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured)) data-preview-featured>
                        </div>
                    </label>

                    <label class="ys-admin-toggle-card">
                        <div>
                            <p class="ys-admin-toggle-title">Keep NEW badge on</p>
                            <p class="ys-admin-toggle-copy">Recent products already show NEW automatically for {{ $newBadgeWindowDays }} days. Turn this on to keep the NEW badge visible longer.</p>
                        </div>
                        <div class="ys-admin-toggle-control">
                            <input type="hidden" name="force_new_badge" value="0">
                            <input type="checkbox" name="force_new_badge" value="1" @checked(old('force_new_badge', $product->force_new_badge)) data-preview-force-new>
                        </div>
                    </label>

                    <div class="ys-admin-detail-panel">
                        <p class="ys-admin-detail-kicker">Live badge preview</p>
                        <h3 class="ys-admin-detail-heading">What shoppers will notice</h3>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <span class="ys-status-pill bg-ys-gold text-ys-ink {{ $previewIsNew ? '' : 'hidden' }}" data-preview-badge-new-secondary>New</span>
                            <span class="ys-status-pill bg-[#e44040] text-white {{ $previewHasSale ? '' : 'hidden' }}" data-preview-badge-sale-secondary>Sale</span>
                            <span class="ys-status-pill bg-white/10 text-ys-ivory {{ $previewFeatured ? '' : 'hidden' }}" data-preview-badge-featured-secondary>Featured</span>
                        </div>
                        <p class="mt-4 text-sm leading-6 text-ys-ivory/48" data-preview-badge-copy>
                            {{ $previewHasSale ? 'SALE is active because the original price is above the selling price.' : 'SALE will appear automatically when an original price is above the selling price.' }}
                        </p>
                    </div>
                </div>
            </section>

            <details class="ys-admin-panel ys-admin-builder-section ys-admin-advanced-section" data-admin-panel @if ($advancedHasErrors) open @endif>
                <summary class="ys-admin-advanced-summary">
                    <div>
                        <p class="ys-admin-detail-kicker">Advanced Settings</p>
                        <h2 class="ys-admin-panel-title">Technical product settings</h2>
                        <p class="ys-admin-subtle">Keep these tucked away unless your team needs tighter control over integrations, import mapping, or storefront metadata.</p>
                    </div>
                    <span class="ys-admin-advanced-summary-icon" aria-hidden="true">&#9881;</span>
                </summary>

                <div class="mt-5 grid gap-4 lg:grid-cols-2">
                    <label class="ys-admin-field">
                        <span class="ys-admin-label">Slug</span>
                        <input type="text" name="slug" value="{{ old('slug', $product->slug) }}" class="ys-admin-input" placeholder="auto-generated-from-product-name">
                        <span class="ys-admin-field-help">Usually auto-generated from the product name unless you need a custom storefront URL.</span>
                    </label>

                    <label class="ys-admin-field">
                        <span class="ys-admin-label">Style Code</span>
                        <input type="text" name="style_code" value="{{ old('style_code', $product->style_code) }}" class="ys-admin-input" placeholder="Example: YS-9001">
                        <span class="ys-admin-field-help">Helpful for imports, supplier coordination, and internal reporting.</span>
                    </label>

                    <label class="ys-admin-field">
                        <span class="ys-admin-label">Primary Image Path or URL</span>
                        <input type="text" name="primary_image_url" value="{{ old('primary_image_url', $product->primary_image_url) }}" class="ys-admin-input" placeholder="images/products/running/shadow-stride.jpg" data-product-image-path>
                        <span class="ys-admin-field-help">Advanced fallback only. Uploads are the recommended workflow for everyday admin use.</span>
                    </label>

                    <label class="ys-admin-field">
                        <span class="ys-admin-label">Image Alt Text</span>
                        <input type="text" name="image_alt" value="{{ old('image_alt', $product->image_alt) }}" class="ys-admin-input">
                        <span class="ys-admin-field-help">Optional accessibility and SEO text for the primary image.</span>
                    </label>

                    <label class="ys-admin-field">
                        <span class="ys-admin-label">Featured Rank</span>
                        <input type="number" min="1" name="featured_rank" value="{{ old('featured_rank', $product->featured_rank) }}" class="ys-admin-input">
                        <span class="ys-admin-field-help">Use this only when you need manual ordering inside the featured collection.</span>
                    </label>

                    <div class="ys-admin-empty-state is-compact">
                        <div class="ys-admin-empty-state-icon">
                            <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5" aria-hidden="true">
                                <path d="M5 12h14M12 5v14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <div>
                            <p class="ys-admin-empty-state-title">Variant-level advanced data</p>
                            <p class="ys-admin-empty-state-copy">Barcode, supplier, cost price, weight, and internal variant labels stay inside each variant card's Advanced details panel.</p>
                        </div>
                    </div>
                </div>
            </details>

            @if ($product->exists)
                <section class="ys-admin-panel ys-admin-builder-section" data-admin-panel>
                    <div class="ys-admin-panel-heading">
                        <div>
                            <h2 class="ys-admin-panel-title">Danger Zone</h2>
                            <p class="ys-admin-subtle">Archive keeps history intact. Permanent delete is only available when this product has no historical dependencies.</p>
                        </div>
                    </div>

                    <div class="ys-admin-danger-grid mt-5">
                        <div class="ys-admin-detail-panel">
                            <p class="ys-admin-detail-kicker">Archive Product</p>
                            <h3 class="ys-admin-detail-heading">Hide safely without losing history</h3>
                            <p class="ys-admin-detail-copy">Archived products stay out of normal storefront, chatbot, and visual-search discovery while preserving stock records, audits, and historical references.</p>
                            <button type="submit" class="ys-admin-button-danger mt-4" form="archive-product-form">Archive product</button>
                        </div>

                        <div class="ys-admin-detail-panel is-danger">
                            <p class="ys-admin-detail-kicker">Delete Product</p>
                            <h3 class="ys-admin-detail-heading">Permanent cleanup for safe test records</h3>
                            <p class="ys-admin-detail-copy">{{ $deletionAssessment['message'] ?? 'Delete remains disabled whenever this product has historical dependencies.' }}</p>

                            @if (($deletionAssessment['can_delete'] ?? false) === true)
                                <button type="submit" class="ys-admin-button-danger mt-4" form="delete-product-form">Delete product permanently</button>
                            @else
                                <button type="button" class="ys-admin-button-danger mt-4 opacity-55" disabled>Delete product unavailable</button>
                                @if (! empty($deletionAssessment['reasons']))
                                    <ul class="mt-4 space-y-2 text-sm text-ys-ivory/52">
                                        @foreach ($deletionAssessment['reasons'] as $reason)
                                            <li>{{ $reason }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            @endif
                        </div>
                    </div>
                </section>
            @endif

            <div class="ys-admin-inline-actions">
                <button type="submit" class="ys-admin-button-primary" data-loading-label="Saving product...">{{ $submitLabel }}</button>
                <a href="{{ route('admin.catalog.products.index') }}" class="ys-admin-button-secondary">Back to products</a>
            </div>
        </div>

        <aside class="ys-admin-product-builder-sidebar space-y-6">
            <section class="ys-admin-panel ys-admin-builder-preview" data-admin-panel>
                <div class="ys-admin-panel-heading">
                    <div>
                        <h2 class="ys-admin-panel-title">Live Storefront Preview</h2>
                        <p class="ys-admin-subtle">A live snapshot of what your shoppers will see as you build the product.</p>
                    </div>
                </div>

                <div class="ys-admin-live-preview-card mt-5" data-live-preview-card>
                    <div class="ys-admin-live-preview-media">
                        <img
                            src="{{ $resolvedPreviewUrl ?? '' }}"
                            alt="{{ old('image_alt', $product->image_alt ?: $previewTitle) }}"
                            class="{{ $resolvedPreviewUrl ? '' : 'hidden' }} ys-admin-live-preview-image"
                            data-live-preview-image
                        >
                        <div class="{{ $resolvedPreviewUrl ? 'hidden' : '' }} ys-admin-live-preview-fallback" data-live-preview-fallback>
                            <img src="{{ asset('brand/yr-logo-full-transparent.png') }}" alt="Ysabelle Retail" class="ys-admin-live-preview-fallback-logo">
                            <p class="ys-admin-live-preview-fallback-title">Image preview</p>
                        </div>

                        <div class="ys-admin-live-preview-badges">
                            <span class="ys-status-pill bg-ys-gold text-ys-ink {{ $previewIsNew ? '' : 'hidden' }}" data-preview-badge-new-card>New</span>
                            <span class="ys-status-pill bg-[#e44040] text-white {{ $previewHasSale ? '' : 'hidden' }}" data-preview-badge-sale-card>Sale</span>
                            <span class="ys-status-pill bg-white/10 text-ys-ivory {{ $previewFeatured ? '' : 'hidden' }}" data-preview-badge-featured-card>Featured</span>
                        </div>

                        <div class="ys-admin-live-preview-availability">
                            <span class="rounded-full px-3 py-1 text-[0.62rem] font-semibold uppercase tracking-[0.18em] {{ $previewAvailabilityClasses }}" data-preview-availability-label>
                                {{ $previewAvailabilityLabel }}
                            </span>
                        </div>
                    </div>

                    <div class="ys-admin-live-preview-body">
                        <div>
                            <p class="ys-admin-live-preview-category" data-preview-category-label>{{ $previewCategory }}</p>
                            <h3 class="ys-admin-live-preview-title" data-preview-title>{{ $previewTitle }}</h3>
                            <p class="ys-admin-live-preview-copy" data-preview-description>{{ $previewDescription }}</p>
                        </div>

                        <div class="ys-admin-live-preview-price-wrap">
                            <p class="ys-admin-live-preview-price" data-preview-price-card>{!! $previewBasePrice !== null ? $previewCurrency.number_format($previewBasePrice, 0) : 'Set price' !!}</p>
                            <p class="ys-admin-live-preview-compare {{ $previewHasSale ? '' : 'hidden' }}" data-preview-compare-price-card>{!! $previewComparePrice !== null ? $previewCurrency.number_format($previewComparePrice, 0) : '' !!}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-5 space-y-3">
                    <div class="ys-admin-detail-panel">
                        <p class="ys-admin-detail-kicker">Visibility summary</p>
                        <h3 class="ys-admin-detail-heading" data-preview-status-label>{{ ucfirst($previewStatus) }}</h3>
                        <p class="ys-admin-detail-copy" data-preview-status-copy>
                            {{ $previewStatus === 'active' ? 'Shoppers can see this product when stock and filters allow it.' : 'This product stays out of the active shopper catalog until you change the status.' }}
                        </p>
                    </div>

                    <div class="ys-admin-detail-panel">
                        <p class="ys-admin-detail-kicker">Inventory summary</p>
                        <h3 class="ys-admin-detail-heading" data-preview-stock-summary>
                            @if (! $previewTrackInventory)
                                Inventory not tracked
                            @else
                                {{ $previewQuantity }} units across active variants
                            @endif
                        </h3>
                        <p class="ys-admin-detail-copy" data-preview-stock-copy>
                            {{ $previewTrackInventory ? 'Low stock alerts use the combined active variant thresholds you set below.' : 'This product will always read as in stock because inventory tracking is turned off.' }}
                        </p>
                    </div>
                </div>
            </section>

            @if ($visibilityDiagnostics)
                <section class="ys-admin-panel ys-admin-builder-preview" data-admin-panel data-visibility-checklist>
                    <div class="ys-admin-panel-heading">
                        <div>
                            <h2 class="ys-admin-panel-title">Product Visibility Checklist</h2>
                            <p class="ys-admin-subtle">A plain-language status report for storefront, chatbot, stock, and visual search readiness.</p>
                        </div>
                    </div>

                    <div class="ys-admin-diagnostic-list mt-5">
                        @foreach ($visibilityDiagnostics['checks'] as $check)
                            <article class="ys-admin-diagnostic-item">
                                <div class="ys-admin-diagnostic-topline">
                                    <h3 class="ys-admin-live-title">{{ $check['label'] }}</h3>
                                    <span class="ys-admin-pill {{ match ($check['state']) {
                                        'pass' => 'ys-admin-pill-success',
                                        'warning' => 'ys-admin-pill-warning',
                                        default => 'ys-admin-pill-danger',
                                    } }}">
                                        {{ ucfirst($check['state']) }}
                                    </span>
                                </div>
                                <p class="ys-admin-live-copy">{{ $check['reason'] }}</p>
                                <p class="ys-admin-diagnostic-fix">{{ $check['recommendation'] }}</p>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
        </aside>

        <template id="variant-template">
            @include('admin.catalog.products._variant-row', ['index' => '__INDEX__', 'variant' => [
                'id' => null,
                'name' => '',
                'sku' => '',
                'barcode' => '',
                'size' => '',
                'color' => '',
                'price' => '',
                'compare_at_price' => '',
                'cost_price' => '',
                'supplier_name' => '',
                'weight_grams' => '',
                'status' => 'active',
                'quantity_on_hand' => 0,
                'reorder_level' => 0,
                'allow_backorder' => false,
            ]])
        </template>
    </div>
</form>

@if ($product->exists)
    <form id="archive-product-form" action="{{ route('admin.catalog.products.destroy', $product) }}" method="POST" data-confirm-message="Archive this product?">
        @csrf
        @method('DELETE')
    </form>

    @if (($deletionAssessment['can_delete'] ?? false) === true)
        <form id="delete-product-form" action="{{ route('admin.catalog.products.purge', $product) }}" method="POST" data-confirm-message="Delete this product permanently? This cannot be undone.">
            @csrf
            @method('DELETE')
        </form>
    @endif
@endif
