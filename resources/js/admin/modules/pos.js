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
            <p class="ys-admin-pos-empty-copy">Search live inventory and tap a product card to build the current sale.</p>
        </div>
    </div>
`;

const resultsEmptyMarkup = `
    <div class="ys-admin-pos-results-empty">
        <p class="ys-admin-pos-empty-title">No products found</p>
        <p class="ys-admin-pos-empty-copy">Try a broader name, SKU, category, or variant search.</p>
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
        category_name: line.category_name,
        price: Number(line.price),
        available_quantity: availableQuantity,
        image_url: line.image_url ?? null,
        image_alt: line.image_alt ?? line.name,
        quantity: Math.max(1, Math.min(Number(line.quantity ?? 1), Math.max(availableQuantity, 1))),
    };
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

    results.innerHTML = state.catalog.map((item) => {
        const selectedLine = state.lines.find((line) => line.id === item.id);
        const selected = Boolean(selectedLine);
        const disabled = item.available_quantity < 1;

        return `
            <button
                type="button"
                class="ys-admin-pos-card ${selected ? 'is-selected' : ''} ${disabled ? 'is-disabled' : ''}"
                data-pos-add="${item.id}"
                ${disabled ? 'disabled' : ''}
            >
                <div class="ys-admin-pos-card-media">
                    ${productImageMarkup(item)}
                    ${selected ? '<span class="ys-admin-pos-card-badge">In sale</span>' : ''}
                </div>

                <div class="ys-admin-pos-card-body">
                    <div>
                        <p class="ys-admin-pos-card-title">${escapeHtml(item.name)}</p>
                        <p class="ys-admin-pos-card-subtitle">${escapeHtml(item.category_name)}</p>
                        <p class="ys-admin-pos-card-variant">${escapeHtml(item.variant_label)}</p>
                        <p class="ys-admin-pos-card-sku">${escapeHtml(item.sku)}</p>
                    </div>

                    <div class="ys-admin-pos-card-foot">
                        <strong class="ys-admin-pos-card-price">${peso}${toCurrency(item.price)}</strong>
                        <span class="ys-admin-pos-card-stock">${disabled ? 'Out of stock' : `x${item.available_quantity}`}</span>
                    </div>
                </div>
            </button>
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

            state.catalog = (payload.data || []).map(normalizeLine);
            state.meta = payload.meta ?? null;

            state.catalog.forEach((item) => {
                const selectedLine = state.lines.find((line) => line.id === item.id);

                if (!selectedLine) {
                    return;
                }

                selectedLine.available_quantity = item.available_quantity;
                selectedLine.quantity = Math.max(1, Math.min(selectedLine.quantity, item.available_quantity));
            });
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
            line.quantity = Math.max(1, Math.min(nextQuantity, line.available_quantity));
            refresh();
            return;
        }

        if (event.target.closest('[data-pos-discount]')) {
            refresh();
        }
    });

    root.addEventListener('click', (event) => {
        const addButton = event.target.closest('[data-pos-add]');

        if (addButton) {
            const item = state.catalog.find((entry) => entry.id === Number(addButton.dataset.posAdd));

            if (item) {
                addLine(item, addButton);
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

            line.quantity = Math.max(1, Math.min(line.quantity + direction, line.available_quantity));
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
