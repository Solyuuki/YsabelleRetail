const MAX_IMAGE_BYTES = 10 * 1024 * 1024;
const ACCEPTED_IMAGE_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];
const ACCEPTED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'];
const LOADING_LABEL = 'Finding similar styles...';
const COMPLETE_LABEL = 'Search complete. You can choose another image anytime.';

export const initInlineVisualSearch = () => {
    const root = document.querySelector('[data-inline-visual-search]');

    if (!root) {
        return;
    }

    const trigger = document.querySelector('[data-inline-visual-search-trigger]');
    const hideButton = root.querySelector('[data-inline-visual-search-hide]');
    const form = root.querySelector('[data-inline-visual-search-form]');
    const input = root.querySelector('[data-inline-visual-search-input]');
    const dropzone = root.querySelector('[data-inline-visual-search-dropzone]');
    const status = root.querySelector('[data-inline-visual-search-status]');
    const fileName = root.querySelector('[data-inline-visual-search-file-name]');
    const statusText = root.querySelector('[data-inline-visual-search-status-text]');
    const resetButton = root.querySelector('[data-inline-visual-search-reset]');
    const clearButton = root.querySelector('[data-inline-visual-search-clear]');
    const inlineEmptyState = root.querySelector('[data-inline-visual-search-empty]');
    const endpoint = root.dataset.visualSearchEndpoint;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const summary = document.querySelector('[data-storefront-grid-summary]');
    const countLabel = document.querySelector('[data-storefront-grid-count]');
    const filterLabel = document.querySelector('[data-storefront-grid-filter-label]');
    const pagination = document.querySelector('[data-storefront-pagination]');
    const emptyState = document.querySelector('[data-storefront-empty-state]');
    const section = summary?.closest('section');
    let grid = document.querySelector('[data-storefront-product-grid]');

    const originalState = {
        countLabel: countLabel?.textContent?.trim() ?? '',
        filterLabel: filterLabel?.textContent?.trim() ?? '',
        filterHidden: filterLabel?.classList.contains('hidden') ?? false,
        gridHtml: grid?.innerHTML ?? '',
        gridPresent: Boolean(grid),
        gridHidden: grid?.classList.contains('hidden') ?? false,
        paginationHidden: pagination?.classList.contains('hidden') ?? false,
        emptyStateHidden: emptyState?.classList.contains('hidden') ?? true,
    };

    let visualModeActive = false;

    const setExpanded = (isOpen) => {
        root.classList.toggle('hidden', !isOpen);
        root.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        trigger?.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    };

    const ensureGrid = () => {
        if (grid) {
            return grid;
        }

        if (!section) {
            return null;
        }

        const createdGrid = document.createElement('div');
        createdGrid.className = 'mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-4';
        createdGrid.dataset.storefrontProductGrid = '';

        const anchor = pagination ?? emptyState;

        if (anchor) {
            anchor.parentNode?.insertBefore(createdGrid, anchor);
        } else {
            section.appendChild(createdGrid);
        }

        grid = createdGrid;

        return grid;
    };

    const clearInlineMessage = () => {
        inlineEmptyState?.classList.add('hidden');

        if (inlineEmptyState) {
            inlineEmptyState.textContent = '';
        }
    };

    const showInlineMessage = (message) => {
        if (!inlineEmptyState) {
            return;
        }

        inlineEmptyState.textContent = message;
        inlineEmptyState.classList.remove('hidden');
    };

    const revealGridCards = (container) => {
        container?.querySelectorAll('[data-reveal]').forEach((element) => {
            element.classList.add('is-visible');
        });
    };

    const resetPanel = () => {
        if (input) {
            input.value = '';
        }

        status?.classList.add('hidden');

        if (fileName) {
            fileName.textContent = '';
        }

        if (statusText) {
            statusText.textContent = 'Ready to search the catalog.';
        }

        dropzone?.classList.remove('is-busy');
        clearButton?.classList.add('hidden');
        clearInlineMessage();
    };

    const showStatus = (name, message) => {
        status?.classList.remove('hidden');

        if (fileName) {
            fileName.textContent = name;
        }

        if (statusText) {
            statusText.textContent = message;
        }
    };

    const validateImage = (file) => {
        if (!file) {
            return 'Select an image first to search the catalog.';
        }

        const extension = String(file.name ?? '')
            .split('.')
            .pop()
            ?.toLowerCase();
        const isImageMime = !file.type || file.type.startsWith('image/');

        if (!isImageMime && !ACCEPTED_IMAGE_TYPES.includes(file.type) && !ACCEPTED_IMAGE_EXTENSIONS.includes(extension)) {
            return 'Please upload a JPG, PNG, WEBP, or HEIC image.';
        }

        if (file.size > MAX_IMAGE_BYTES) {
            return 'Please use an image smaller than 10 MB.';
        }

        return null;
    };

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const renderProductCard = (product) => `
        <article class="group overflow-hidden rounded-[1.75rem] border border-white/7 bg-ys-panel/90 shadow-[0_10px_60px_rgba(0,0,0,0.35)] transition duration-500 hover:-translate-y-1 hover:border-ys-gold/30 hover:shadow-[0_18px_75px_rgba(0,0,0,0.55)]" data-reveal>
            <a href="${escapeHtml(product.url)}" class="block">
                <div class="relative aspect-[4/4.2] overflow-hidden border-b border-white/6 bg-black">
                    ${
                        product.image_url
                            ? `<img src="${escapeHtml(product.image_url)}" alt="${escapeHtml(product.image_alt)}" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.035]">`
                            : `<div class="flex h-full w-full items-center justify-center px-6 text-center text-sm font-medium text-ys-ivory/54">${escapeHtml(product.name)}</div>`
                    }
                    <div class="absolute left-5 top-5 flex gap-2">
                        ${product.is_featured ? '<span class="ys-status-pill bg-ys-gold text-ys-ink">New</span>' : ''}
                        ${product.compare_at_price ? '<span class="ys-status-pill bg-[#e44040] text-white">Sale</span>' : ''}
                    </div>
                </div>
                <div class="space-y-4.5 p-6">
                    <div class="flex items-start justify-between gap-5">
                        <div>
                            <h3 class="text-[1.18rem] font-semibold leading-6 text-ys-ivory transition group-hover:text-ys-gold">${escapeHtml(product.name)}</h3>
                            <p class="mt-1.5 text-[0.72rem] uppercase tracking-[0.3em] text-ys-ivory/42">${escapeHtml(product.category ?? 'Collection')}</p>
                        </div>
                        <div class="flex items-center gap-1.5 text-[0.95rem] text-ys-gold">
                            <svg class="h-4 w-4 fill-current" viewBox="0 0 20 20">
                                <path d="m10 1.7 2.52 5.1 5.63.82-4.08 3.98.96 5.62L10 14.54l-5.03 2.65.96-5.62L1.85 7.6l5.63-.82L10 1.7Z" />
                            </svg>
                            <span>${escapeHtml(Number(product.rating_average ?? 0).toFixed(1))}</span>
                        </div>
                    </div>
                    <div class="flex items-end justify-between gap-5">
                        <div>
                            <p class="text-[1.2rem] font-semibold text-ys-ivory">${escapeHtml(product.price_label)}</p>
                            ${product.compare_at_price_label ? `<p class="mt-1 text-[0.96rem] text-ys-ivory/35 line-through">${escapeHtml(product.compare_at_price_label)}</p>` : ''}
                        </div>
                        <span class="text-[0.76rem] font-semibold uppercase tracking-[0.28em] text-ys-ivory/42 transition group-hover:text-ys-gold">Explore</span>
                    </div>
                </div>
            </a>
        </article>
    `;

    const applyVisualGrid = (matchedProducts) => {
        const targetGrid = ensureGrid();

        if (!targetGrid || !countLabel) {
            return;
        }

        visualModeActive = true;
        clearButton?.classList.remove('hidden');
        clearInlineMessage();

        if (pagination) {
            pagination.classList.add('hidden');
        }

        if (filterLabel) {
            filterLabel.classList.add('hidden');
        }

        if (emptyState) {
            emptyState.classList.add('hidden');
        }

        targetGrid.classList.remove('hidden');

        if (matchedProducts.length === 0) {
            targetGrid.innerHTML = '';
            countLabel.textContent = 'Showing closest styles from your image';
            showInlineMessage('No nearby styles were available from the current catalog.');
            return;
        }

        targetGrid.innerHTML = matchedProducts.map(renderProductCard).join('');
        revealGridCards(targetGrid);
        countLabel.textContent = 'Showing closest styles from your image';
    };

    const restoreOriginalGrid = () => {
        visualModeActive = false;
        clearButton?.classList.add('hidden');
        clearInlineMessage();

        if (countLabel) {
            countLabel.textContent = originalState.countLabel;
        }

        if (filterLabel) {
            filterLabel.textContent = originalState.filterLabel;
            filterLabel.classList.toggle('hidden', originalState.filterHidden);
        }

        if (pagination) {
            pagination.classList.toggle('hidden', originalState.paginationHidden);
        }

        if (originalState.gridPresent && grid) {
            grid.innerHTML = originalState.gridHtml;
            grid.classList.toggle('hidden', originalState.gridHidden);
            revealGridCards(grid);
        }

        if (!originalState.gridPresent && grid) {
            grid.remove();
            grid = null;
        }

        if (emptyState) {
            emptyState.classList.toggle('hidden', originalState.emptyStateHidden);
        }
    };

    const submitImage = async (file) => {
        if (!endpoint || !form) {
            return;
        }

        const validationMessage = validateImage(file);

        if (validationMessage) {
            showStatus(file?.name ?? '', validationMessage);
            showInlineMessage(validationMessage);
            return;
        }

        showStatus(file.name, LOADING_LABEL);
        dropzone?.classList.add('is-busy');
        clearInlineMessage();

        const formData = new FormData(form);

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: formData,
            });

            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.message ?? firstValidationError(payload) ?? 'Visual Search could not process that image.');
            }

            applyVisualGrid(Array.isArray(payload.products) ? payload.products : []);
            showStatus(file.name, COMPLETE_LABEL);
        } catch (error) {
            restoreOriginalGrid();
            const message = error instanceof Error ? error.message : 'Visual Search is temporarily unavailable. Please try again.';
            showStatus(file.name, message);
            showInlineMessage(message);
        } finally {
            dropzone?.classList.remove('is-busy');
        }
    };

    const assignFile = (file) => {
        if (!file || !input) {
            return;
        }

        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        input.files = dataTransfer.files;
        submitImage(file);
    };

    trigger?.addEventListener('click', () => {
        setExpanded(true);
        dropzone?.focus();
    });

    hideButton?.addEventListener('click', () => {
        setExpanded(false);
    });

    resetButton?.addEventListener('click', () => {
        input?.click();
    });

    clearButton?.addEventListener('click', () => {
        resetPanel();
        restoreOriginalGrid();
    });

    dropzone?.addEventListener('click', () => {
        input?.click();
    });

    dropzone?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        event.preventDefault();
        input?.click();
    });

    dropzone?.addEventListener('dragover', (event) => {
        event.preventDefault();
        dropzone.classList.add('is-dragover');
    });

    dropzone?.addEventListener('dragleave', () => {
        dropzone.classList.remove('is-dragover');
    });

    dropzone?.addEventListener('drop', (event) => {
        event.preventDefault();
        dropzone.classList.remove('is-dragover');
        const file = event.dataTransfer?.files?.[0];

        if (!file) {
            return;
        }

        assignFile(file);
    });

    input?.addEventListener('change', () => {
        const file = input.files?.[0];

        if (!file) {
            return;
        }

        submitImage(file);
    });

    setExpanded(false);
    resetPanel();
};

const firstValidationError = (payload) => {
    const errors = payload?.errors;

    if (!errors || typeof errors !== 'object') {
        return null;
    }

    const firstError = Object.values(errors)[0];

    return Array.isArray(firstError) ? firstError[0] : null;
};
