const currency = new Intl.NumberFormat('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const peso = '\u20B1';

const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

const toCurrency = (amount) => currency.format(Number(amount ?? 0));

const productImageMarkup = (item) => {
    if (item.image_url) {
        return `
            <img
                src="${escapeHtml(item.image_url)}"
                alt="${escapeHtml(item.image_alt || item.name)}"
                class="ys-admin-pos-card-image"
                loading="lazy"
                decoding="async"
            >
        `;
    }

    return `
        <div class="ys-admin-pos-card-placeholder">
            <svg class="h-9 w-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                <path d="M4 7.5 12 4l8 3.5L12 11 4 7.5Z"></path>
                <path d="M4 7.5V16.5L12 20l8-3.5V7.5"></path>
                <path d="M12 11v9"></path>
            </svg>
        </div>
    `;
};

const cartEmptyMarkup = `
    <div class="ys-admin-pos-empty-state">
        <span class="ys-admin-pos-empty-icon">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                <path d="M5 6h14v12H5z"></path>
                <path d="M8 10h8M8 14h4"></path>
            </svg>
        </span>
        <div>
            <p class="ys-admin-pos-empty-title">Add products to start</p>
            <p class="ys-admin-pos-empty-copy">Search live inventory, choose the exact size and SKU, then add to the current sale.</p>
        </div>
    </div>
`;

const resultsEmptyMarkup = `
    <div class="ys-admin-pos-results-empty">
        <p class="ys-admin-pos-empty-title">No products found</p>
        <p class="ys-admin-pos-empty-copy">Try a broader product, SKU, category, color, or size search.</p>
    </div>
`;

const loadingMarkup = Array.from({ length: 4 }, () => `
    <div class="ys-admin-pos-card is-skeleton" aria-hidden="true">
        <div class="ys-admin-pos-card-media"></div>
        <div class="ys-admin-pos-card-body">
            <span class="ys-admin-pos-skeleton-line w-4/5"></span>
            <span class="ys-admin-pos-skeleton-line w-2/5"></span>
            <span class="ys-admin-pos-skeleton-line w-1/2"></span>
        </div>
    </div>
`).join('');

const normalizeLine = (line) => {
    const availableQuantity = Number(line.available_quantity ?? 0);

    return {
        id: Number(line.id),
        product_id: Number(line.product_id ?? 0),
        sku: line.sku,
        name: line.name,
        variant_name: line.variant_name,
        variant_label: line.variant_label ?? line.variant_name,
        size: line.size ?? null,
        color: line.color ?? null,
        category_name: line.category_name,
        price: Number(line.price),
        available_quantity: availableQuantity,
        image_url: line.image_url ?? null,
        image_alt: line.image_alt ?? line.name,
        quantity: Math.max(1, Math.min(Number(line.quantity ?? 1), Math.max(availableQuantity, 1))),
    };
};

const normalizeCatalogVariant = (variant) => ({
    ...normalizeLine(variant),
    is_match: Boolean(variant.is_match),
});

const normalizeCatalogGroup = (group) => ({
    id: String(group.id),
    product_id: Number(group.product_id ?? 0),
    name: group.name,
    category_name: group.category_name,
    color: group.color ?? 'Unspecified color',
    image_url: group.image_url ?? null,
    image_alt: group.image_alt ?? group.name,
    price: Number(group.price ?? 0),
    price_min: Number(group.price_min ?? group.price ?? 0),
    price_max: Number(group.price_max ?? group.price ?? 0),
    has_price_range: Boolean(group.has_price_range),
    available_quantity: Number(group.available_quantity ?? 0),
    variant_count: Number(group.variant_count ?? 0),
    badges: Array.isArray(group.badges) ? group.badges : [],
    matched_variant_ids: Array.isArray(group.matched_variant_ids)
        ? group.matched_variant_ids.map((id) => Number(id))
        : [],
    variants: Array.isArray(group.variants)
        ? group.variants.map(normalizeCatalogVariant)
        : [],
});

const formatCatalogPrice = (group) => {
    if (group.has_price_range) {
        return `${peso}${toCurrency(group.price_min)} - ${peso}${toCurrency(group.price_max)}`;
    }

    return `${peso}${toCurrency(group.price)}`;
};

const formatVariantSize = (variant) => variant.size ? `Size ${variant.size}` : variant.variant_name;

const formatVariantOption = (variant, showPrice = true) => {
    const parts = [];

    parts.push(formatVariantSize(variant));
    parts.push(variant.sku);
    parts.push(`${variant.available_quantity} in stock`);

    if (showPrice) {
        parts.push(`${peso}${toCurrency(variant.price)}`);
    }

    return parts.join(' / ');
};

const formatVariantPreview = (variant, showPrice = true) => {
    if (!variant) {
        return 'Choose an exact size and SKU to preview the variant details here.';
    }

    const details = [
        formatVariantSize(variant),
        `SKU ${variant.sku}`,
        `${variant.available_quantity} in stock`,
    ];

    if (showPrice) {
        details.push(`${peso}${toCurrency(variant.price)}`);
    }

    return details.join(' / ');
};

const getDiscountAmount = (root, subtotal) => {
    const input = root.querySelector('[data-pos-discount]');
    const raw = Number(input?.value ?? 0);
    const discount = Number.isFinite(raw) ? raw : 0;
    const clamped = Math.min(Math.max(discount, 0), Math.max(subtotal, 0));

    if (input) {
        input.value = clamped.toFixed(2).replace(/\.00$/, '');
    }

    return clamped;
};

const renderCart = (root, state) => {
    const body = root.querySelector('[data-pos-cart]');
    const hidden = root.querySelector('[name="lines_json"]');
    const subtotalNode = root.querySelector('[data-pos-subtotal]');
    const discountNode = root.querySelector('[data-pos-discount-total]');
    const totalNode = root.querySelector('[data-pos-total]');
    const submit = root.querySelector('[data-pos-submit]');

    if (!body || !hidden || !subtotalNode || !discountNode || !totalNode || !submit) {
        return;
    }

    if (state.lines.length === 0) {
        body.innerHTML = cartEmptyMarkup;
        hidden.value = '[]';
        subtotalNode.textContent = '0.00';
        discountNode.textContent = '0.00';
        totalNode.textContent = '0.00';
        submit.disabled = true;
        submit.textContent = 'Cart is empty';
        return;
    }

    body.innerHTML = state.lines.map((line, index) => `
        <div class="ys-admin-pos-cart-item">
            <div class="ys-admin-pos-cart-main">
                <div>
                    <p class="ys-admin-pos-cart-title">${escapeHtml(line.name)}</p>
                    <p class="ys-admin-pos-cart-meta">${escapeHtml(line.variant_label)} / ${escapeHtml(line.sku)}</p>
                </div>
                <button type="button" class="ys-admin-pos-cart-remove" data-pos-remove="${index}">Remove</button>
            </div>

            <div class="ys-admin-pos-cart-foot">
                <div class="ys-admin-pos-stepper">
                    <button type="button" class="ys-admin-pos-stepper-btn" data-pos-step="${index}" data-direction="-1" aria-label="Decrease quantity">-</button>
                    <input
                        type="number"
                        min="1"
                        max="${Math.max(line.available_quantity, 1)}"
                        value="${line.quantity}"
                        class="ys-admin-pos-stepper-input"
                        data-pos-quantity="${index}"
                    >
                    <button type="button" class="ys-admin-pos-stepper-btn" data-pos-step="${index}" data-direction="1" aria-label="Increase quantity">+</button>
                </div>

                <div class="text-right">
                    <p class="ys-admin-pos-cart-price">${peso}${toCurrency(line.quantity * line.price)}</p>
                    <p class="ys-admin-pos-cart-stock">${line.available_quantity} in stock</p>
                </div>
            </div>
        </div>
    `).join('');

    const subtotal = state.lines.reduce((sum, line) => sum + (line.quantity * line.price), 0);
    const discount = getDiscountAmount(root, subtotal);
    const total = Math.max(subtotal - discount, 0);

    hidden.value = JSON.stringify(state.lines.map(({ id, quantity }) => ({
        variant_id: id,
        quantity,
    })));
    subtotalNode.textContent = toCurrency(subtotal);
    discountNode.textContent = toCurrency(discount);
    totalNode.textContent = toCurrency(total);
    submit.disabled = false;
    submit.textContent = `Complete sale - ${peso}${toCurrency(total)}`;
};

const buildPaginationMarkup = (meta) => {
    if (!meta || meta.last_page <= 1) {
        return '';
    }

    const pages = [];
    const start = Math.max(1, meta.current_page - 1);
    const end = Math.min(meta.last_page, meta.current_page + 1);

    for (let page = start; page <= end; page += 1) {
        pages.push(`
            ${page === meta.current_page
                ? `<span aria-current="page" class="ys-admin-pagination-link is-active">${page}</span>`
                : `<button type="button" class="ys-admin-pagination-link" data-pos-page="${page}">${page}</button>`}
        `);
    }

    return `
        <nav class="ys-admin-pagination" aria-label="POS catalog pagination">
            <div class="ys-admin-pagination-summary">
                Showing ${meta.from ?? 0}-${meta.to ?? 0} of ${meta.total ?? 0} products
            </div>
            <div class="ys-admin-pagination-links">
                ${meta.current_page > 1
                    ? `<button type="button" class="ys-admin-pagination-link" data-pos-page="${meta.current_page - 1}">Prev</button>`
                    : '<span class="ys-admin-pagination-link is-disabled">Prev</span>'}
                ${pages.join('')}
                ${meta.current_page < meta.last_page
                    ? `<button type="button" class="ys-admin-pagination-link" data-pos-page="${meta.current_page + 1}">Next</button>`
                    : '<span class="ys-admin-pagination-link is-disabled">Next</span>'}
            </div>
        </nav>
    `;
};

const renderResults = (root, state) => {
    const results = root.querySelector('[data-pos-results]');
    const label = root.querySelector('[data-pos-results-label]');
    const summary = root.querySelector('[data-pos-results-summary]');
    const pagination = root.querySelector('[data-pos-pagination]');

    if (!results || !label || !summary || !pagination) {
        return;
    }

    if (state.loading) {
        label.textContent = 'Loading live inventory...';
        summary.textContent = 'Please wait';
        results.innerHTML = loadingMarkup;
        pagination.innerHTML = '';
        return;
    }

    label.textContent = state.searchTerm === ''
        ? 'Showing live inventory'
        : `Search results for "${state.searchTerm}"`;

    if (state.meta) {
        summary.textContent = `Page ${state.meta.current_page} of ${state.meta.last_page}`;
    } else {
        summary.textContent = '8 per page';
    }

    if (state.catalog.length === 0) {
        results.innerHTML = resultsEmptyMarkup;
        pagination.innerHTML = '';
        return;
    }

    results.innerHTML = state.catalog.map((group) => {
        const selectedVariantId = Number(state.selectedVariants[group.id] ?? 0);
        const selectedVariant = group.variants.find((variant) => variant.id === selectedVariantId) ?? null;
        const selectedLineCount = state.lines.filter((line) => group.variants.some((variant) => variant.id === line.id)).length;
        const hasSelection = selectedVariant !== null;
        const canAdd = hasSelection && selectedVariant.available_quantity > 0;
        const stockLabel = group.available_quantity > 0
            ? `${group.available_quantity} total in stock`
            : 'Out of stock';
        const pickerPreview = formatVariantPreview(selectedVariant, group.has_price_range);
        const badges = [
            ...group.badges,
            ...(selectedLineCount > 0 ? [selectedLineCount > 1 ? `${selectedLineCount} in sale` : 'In sale'] : []),
        ];

        return `
            <article class="ys-admin-pos-card ${selectedLineCount > 0 ? 'is-selected' : ''} ${group.available_quantity < 1 ? 'is-disabled' : ''}" data-pos-card="${escapeHtml(group.id)}">
                <div class="ys-admin-pos-card-media">
                    ${productImageMarkup(group)}
                    ${badges.length > 0 ? `
                        <div class="ys-admin-pos-card-badges">
                            ${badges.map((badge) => `<span class="ys-admin-pos-card-badge">${escapeHtml(badge)}</span>`).join('')}
                        </div>
                    ` : ''}
                </div>

                <div class="ys-admin-pos-card-body">
                    <div>
                        <p class="ys-admin-pos-card-title">${escapeHtml(group.name)}</p>
                        <p class="ys-admin-pos-card-subtitle">${escapeHtml(group.category_name)}</p>
                        <p class="ys-admin-pos-card-variant">${escapeHtml(group.color)}</p>
                        <p class="ys-admin-pos-card-sku">${escapeHtml(`${group.variant_count} size options`)}</p>
                    </div>

                    <div class="ys-admin-pos-card-foot">
                        <strong class="ys-admin-pos-card-price">${formatCatalogPrice(group)}</strong>
                        <span class="ys-admin-pos-card-stock">${escapeHtml(stockLabel)}</span>
                    </div>

                    <label class="ys-admin-pos-picker">
                        <span class="ys-admin-pos-picker-label">Size / SKU</span>
                        <select
                            class="ys-admin-pos-picker-select"
                            data-pos-variant-picker="${escapeHtml(group.id)}"
                            title="${escapeHtml(pickerPreview)}"
                        >
                            <option value="">Choose exact size and SKU</option>
                            ${group.variants.map((variant) => `
                                <option
                                    value="${variant.id}"
                                    ${variant.id === selectedVariantId ? 'selected' : ''}
                                    ${variant.available_quantity < 1 ? 'disabled' : ''}
                                >
                                    ${escapeHtml(formatVariantOption(variant, group.has_price_range))}
                                </option>
                            `).join('')}
                        </select>
                        <p class="ys-admin-pos-picker-preview ${hasSelection ? 'is-selected' : 'is-placeholder'}">
                            ${escapeHtml(pickerPreview)}
                        </p>
                    </label>

                    <button
                        type="button"
                        class="ys-admin-button-primary ys-admin-pos-card-action"
                        data-pos-add-group="${escapeHtml(group.id)}"
                        ${canAdd ? '' : 'disabled'}
                    >
                        ${hasSelection ? 'Add selected variant' : 'Choose size to add'}
                    </button>
                </div>
            </article>
        `;
    }).join('');

    pagination.innerHTML = buildPaginationMarkup(state.meta);
};

const animateCardPress = (node) => {
    node.classList.remove('is-pressed');
    void node.offsetWidth;
    node.classList.add('is-pressed');
    window.setTimeout(() => node.classList.remove('is-pressed'), 180);
};

export const initAdminPos = () => {
    const root = document.querySelector('[data-admin-pos]');

    if (!root) {
        return;
    }

    const endpoint = root.dataset.searchEndpoint;
    const search = root.querySelector('[data-pos-search]');
    const oldLines = JSON.parse(root.dataset.oldLines || '[]');
    const state = {
        lines: oldLines
            .filter((line) => line && typeof line.id === 'number')
            .map(normalizeLine),
        catalog: [],
        selectedVariants: {},
        searchTerm: '',
        loading: false,
        requestId: 0,
        page: 1,
        meta: null,
    };

    let searchTimeout = null;

    const refresh = () => {
        renderCart(root, state);
        renderResults(root, state);
    };

    const addLine = (item, trigger = null) => {
        if (item.available_quantity < 1) {
            return;
        }

        const existing = state.lines.find((line) => line.id === item.id);

        if (existing) {
            existing.quantity = Math.min(existing.quantity + 1, existing.available_quantity);
            existing.available_quantity = item.available_quantity;
        } else {
            state.lines.push({
                ...normalizeLine(item),
                quantity: 1,
            });
        }

        if (trigger) {
            animateCardPress(trigger);
        }

        refresh();
    };

    const syncLineAvailability = () => {
        const variantsById = new Map();

        state.catalog.forEach((group) => {
            group.variants.forEach((variant) => {
                variantsById.set(variant.id, variant);
            });

            const selectedVariantId = Number(state.selectedVariants[group.id] ?? 0);

            if (selectedVariantId > 0 && !group.variants.some((variant) => variant.id === selectedVariantId)) {
                delete state.selectedVariants[group.id];
            }
        });

        state.lines.forEach((line) => {
            const current = variantsById.get(line.id);

            if (!current) {
                return;
            }

            line.available_quantity = current.available_quantity;
            line.price = current.price;
            line.variant_label = current.variant_label;
            line.sku = current.sku;
            line.size = current.size;
            line.color = current.color;
            line.quantity = Math.max(1, Math.min(line.quantity, Math.max(current.available_quantity, 1)));
        });
    };

    const loadResults = async (nextPage = state.page) => {
        if (!endpoint) {
            return;
        }

        const currentRequestId = state.requestId + 1;
        state.requestId = currentRequestId;
        state.page = nextPage;
        state.searchTerm = search?.value?.trim() || '';
        state.loading = true;
        refresh();

        try {
            const params = new URLSearchParams({
                search: state.searchTerm,
                page: String(state.page),
            });

            const response = await fetch(`${endpoint}?${params.toString()}`, {
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error(`POS search failed with ${response.status}`);
            }

            const payload = await response.json();

            if (state.requestId !== currentRequestId) {
                return;
            }

            state.catalog = (payload.data || []).map(normalizeCatalogGroup);
            state.meta = payload.meta ?? null;
            syncLineAvailability();
        } catch (error) {
            if (state.requestId !== currentRequestId) {
                return;
            }

            state.catalog = [];
            state.meta = null;
        } finally {
            if (state.requestId === currentRequestId) {
                state.loading = false;
                refresh();
            }
        }
    };

    search?.addEventListener('input', () => {
        if (searchTimeout !== null) {
            window.clearTimeout(searchTimeout);
        }

        searchTimeout = window.setTimeout(() => {
            state.page = 1;
            loadResults(1);
        }, 300);
    });

    root.addEventListener('input', (event) => {
        const quantityField = event.target.closest('[data-pos-quantity]');

        if (quantityField) {
            const line = state.lines[Number(quantityField.dataset.posQuantity)];

            if (!line) {
                return;
            }

            const nextQuantity = Number(quantityField.value || 1);
            line.quantity = Math.max(1, Math.min(nextQuantity, Math.max(line.available_quantity, 1)));
            refresh();
            return;
        }

        if (event.target.closest('[data-pos-discount]')) {
            refresh();
        }
    });

    root.addEventListener('change', (event) => {
        const picker = event.target.closest('[data-pos-variant-picker]');

        if (!picker) {
            return;
        }

        const groupId = picker.dataset.posVariantPicker;
        const selectedVariantId = picker.value ? Number(picker.value) : null;

        if (!groupId) {
            return;
        }

        if (selectedVariantId && Number.isFinite(selectedVariantId)) {
            state.selectedVariants[groupId] = selectedVariantId;
        } else {
            delete state.selectedVariants[groupId];
        }

        refresh();
    });

    root.addEventListener('click', (event) => {
        const addButton = event.target.closest('[data-pos-add-group]');

        if (addButton) {
            const group = state.catalog.find((entry) => entry.id === String(addButton.dataset.posAddGroup));
            const selectedVariantId = group ? Number(state.selectedVariants[group.id] ?? 0) : 0;
            const variant = group?.variants.find((entry) => entry.id === selectedVariantId);

            if (variant) {
                const card = addButton.closest('[data-pos-card]');
                addLine(variant, card || addButton);
            }

            return;
        }

        const removeButton = event.target.closest('[data-pos-remove]');

        if (removeButton) {
            state.lines.splice(Number(removeButton.dataset.posRemove), 1);
            refresh();
            return;
        }

        const stepButton = event.target.closest('[data-pos-step]');

        if (stepButton) {
            const index = Number(stepButton.dataset.posStep);
            const direction = Number(stepButton.dataset.direction || 0);
            const line = state.lines[index];

            if (!line) {
                return;
            }

            line.quantity = Math.max(1, Math.min(line.quantity + direction, Math.max(line.available_quantity, 1)));
            refresh();
            return;
        }

        const pageButton = event.target.closest('[data-pos-page]');

        if (pageButton) {
            const page = Number(pageButton.dataset.posPage || 1);

            if (Number.isFinite(page) && page > 0) {
                loadResults(page);
            }
        }
    });

    refresh();
    loadResults();
};
