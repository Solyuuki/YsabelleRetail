const formatPeso = (value) => {
    if (typeof value !== 'number' || Number.isNaN(value)) {
        return null;
    }

    return `\u20b1${new Intl.NumberFormat('en-PH', {
        maximumFractionDigits: 0,
    }).format(value)}`;
};

const normalizeRelativeUrl = (origin, value) => {
    const trimmed = (value || '').trim();

    if (!trimmed) {
        return null;
    }

    if (/^https?:\/\//i.test(trimmed)) {
        return trimmed;
    }

    if (trimmed.startsWith('/')) {
        return `${origin}${trimmed}`;
    }

    return `${origin}/${trimmed}`;
};

const availabilityClasses = (state) => {
    switch (state) {
        case 'in_stock':
            return 'bg-[#11311f] text-[#9fe1b1]';
        case 'low_stock':
            return 'bg-[#38260c] text-[#f0c36f]';
        case 'available_for_backorder':
            return 'bg-[#112a3f] text-[#9fd4ff]';
        case 'inactive':
            return 'bg-[#2f2b36] text-[#ddd3f0]';
        default:
            return 'bg-[#411415] text-[#ffb0b0]';
    }
};

const replaceVariantIndex = (value, index) => {
    return value.replace(/variants\[[^\]]+\]/g, `variants[${index}]`);
};

const setHidden = (node, hidden) => {
    if (!node) {
        return;
    }

    node.classList.toggle('hidden', hidden);
};

const toggleNodeGroup = (root, selectors, visible) => {
    root.querySelectorAll(selectors).forEach((node) => setHidden(node, !visible));
};

const buildStatusCopy = (status) => {
    switch (status) {
        case 'active':
            return 'Shoppers can see this product when stock and filters allow it.';
        case 'draft':
            return 'This product stays hidden from shoppers until you activate it.';
        default:
            return 'This product is kept out of the active shopper catalog while preserving its history.';
    }
};

const labelForAvailability = (root, state) => {
    switch (state) {
        case 'in_stock':
            return root.dataset.labelInStock || 'In Stock';
        case 'low_stock':
            return root.dataset.labelLowStock || 'Low Stock';
        case 'available_for_backorder':
            return root.dataset.labelBackorder || 'Available for Backorder';
        case 'inactive':
            return root.dataset.labelInactive || 'Inactive';
        default:
            return root.dataset.labelSoldOut || 'Sold Out';
    }
};

const buildStockCopy = ({ trackInventory, hasPrice, hasActiveVariants, quantity, reorderLevel, backorder }) => {
    if (!trackInventory) {
        return 'This product will always read as in stock because inventory tracking is turned off.';
    }

    if (!hasActiveVariants) {
        return 'Add at least one active variant so shoppers can buy this product.';
    }

    if (!hasPrice) {
        return 'Add at least one priced active variant below to complete the storefront stock and price preview.';
    }

    if (quantity <= 0 && backorder) {
        return 'The preview reads as available for backorder because active variants allow selling at zero stock.';
    }

    if (quantity <= 0) {
        return 'The preview currently reads as sold out because active variants have no stock and backorder is disabled.';
    }

    if (reorderLevel > 0 && quantity <= reorderLevel) {
        return 'The combined active variant quantity is at or below the low stock alert threshold.';
    }

    return 'Active variants currently have enough stock for the storefront to show this product as available.';
};

const disableActionButton = (button, disabled) => {
    if (!button) {
        return;
    }

    button.disabled = disabled;
    button.classList.toggle('opacity-45', disabled);
    button.classList.toggle('cursor-not-allowed', disabled);
};

const parseErrorIndexes = (value) => {
    return String(value || '')
        .split(',')
        .map((item) => item.trim())
        .filter((item) => item !== '')
        .map((item) => Number.parseInt(item, 10))
        .filter((item) => Number.isInteger(item) && item >= 0);
};

const initVariantBuilder = (root) => {
    const list = root.querySelector('[data-variant-list]');
    const addButtons = root.querySelectorAll('[data-variant-add]');
    const template = root.querySelector('#variant-template') || document.querySelector('#variant-template');
    const searchInput = root.querySelector('[data-variant-search]');
    const previousButton = root.querySelector('[data-variant-prev]');
    const nextButton = root.querySelector('[data-variant-next]');
    const pageLabel = root.querySelector('[data-variant-page-label]');
    const countLabel = root.querySelector('[data-variant-count]');
    const state = {
        page: 1,
        query: '',
    };
    const perPage = Math.max(1, Number.parseInt(root.dataset.variantPerPage || '4', 10) || 4);
    const errorIndexes = parseErrorIndexes(root.dataset.initialErrorVariantIndexes);

    if (!list || !template) {
        return {
            rows: () => [],
            refresh: () => {},
        };
    }

    const rows = () => Array.from(list.querySelectorAll('[data-variant-row]'));

    const rowSearchText = (row) => {
        return [
            row.querySelector('[data-variant-size]')?.value || '',
            row.querySelector('[data-variant-color]')?.value || '',
            row.querySelector('[data-variant-sku]')?.value || '',
            row.querySelector('input[name*="[name]"]')?.value || '',
        ].join(' ').trim().toLowerCase();
    };

    const syncVariantSummary = (row, index) => {
        const size = row.querySelector('[data-variant-size]')?.value.trim() || '';
        const color = row.querySelector('[data-variant-color]')?.value.trim() || '';
        const sku = row.querySelector('[data-variant-sku]')?.value.trim() || '';
        const title = row.querySelector('[data-variant-title]');
        const summary = row.querySelector('[data-variant-summary]');
        const removeButton = row.querySelector('[data-variant-remove]');

        row.dataset.variantExisting = row.querySelector('[data-variant-id]')?.value ? '1' : '0';

        if (title) {
            title.textContent = size || color
                ? [size, color].filter(Boolean).join(' / ')
                : `Variant ${index + 1}`;
        }

        if (summary) {
            summary.textContent = sku
                ? `SKU ${sku}`
                : 'Add size, color, and SKU so your team can identify this stock option quickly.';
        }

        disableActionButton(removeButton, rows().length <= 1);
    };

    const filteredRows = () => {
        const query = state.query.trim().toLowerCase();

        if (!query) {
            return rows();
        }

        return rows().filter((row) => rowSearchText(row).includes(query));
    };

    const focusPageForRow = (focusRow, activeRows) => {
        if (!focusRow) {
            return;
        }

        const rowIndex = activeRows.indexOf(focusRow);

        if (rowIndex < 0) {
            return;
        }

        state.page = Math.floor(rowIndex / perPage) + 1;
    };

    const applyPagination = (focusRow = null) => {
        const allRows = rows();
        const activeRows = filteredRows();
        const totalPages = Math.max(1, Math.ceil(Math.max(activeRows.length, 1) / perPage));

        focusPageForRow(focusRow, activeRows);
        state.page = Math.min(Math.max(state.page, 1), totalPages);

        allRows.forEach((row) => {
            row.classList.add('hidden');
            row.setAttribute('aria-hidden', 'true');
        });

        activeRows
            .slice((state.page - 1) * perPage, state.page * perPage)
            .forEach((row) => {
                row.classList.remove('hidden');
                row.setAttribute('aria-hidden', 'false');
            });

        if (countLabel) {
            countLabel.textContent = String(allRows.length);
        }

        if (pageLabel) {
            const querySuffix = state.query ? ` • ${activeRows.length} match${activeRows.length === 1 ? '' : 'es'}` : '';
            pageLabel.textContent = `Page ${state.page} of ${totalPages}${querySuffix}`;
        }

        disableActionButton(previousButton, state.page <= 1);
        disableActionButton(nextButton, state.page >= totalPages);
    };

    const reindexRows = (focusRow = null) => {
        rows().forEach((row, index) => {
            row.querySelectorAll('input, select, textarea').forEach((field) => {
                if (field.name) {
                    field.name = replaceVariantIndex(field.name, index);
                }
            });

            syncVariantSummary(row, index);
        });

        applyPagination(focusRow);
    };

    const cloneVariantRow = (sourceRow) => {
        const clone = sourceRow.cloneNode(true);
        const idField = clone.querySelector('[data-variant-id]');
        const skuField = clone.querySelector('[data-variant-sku]');
        const barcodeField = clone.querySelector('input[name*="[barcode]"]');
        const quantityField = clone.querySelector('[data-variant-quantity]');

        if (idField) {
            idField.value = '';
        }

        if (skuField) {
            skuField.value = '';
        }

        if (barcodeField) {
            barcodeField.value = '';
        }

        if (quantityField) {
            quantityField.value = '0';
        }

        clone.querySelectorAll('details').forEach((details) => {
            details.open = false;
        });

        return clone;
    };

    const addVariantFromTemplate = () => {
        const nextIndex = rows().length;
        list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(nextIndex)));
        const newRow = rows().at(-1) || null;
        reindexRows(newRow);
        root.dispatchEvent(new CustomEvent('product-builder:changed'));

        newRow?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        newRow?.querySelector('[data-variant-size]')?.focus();
    };

    addButtons.forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            addVariantFromTemplate();
        });
    });

    previousButton?.addEventListener('click', () => {
        state.page -= 1;
        applyPagination();
    });

    nextButton?.addEventListener('click', () => {
        state.page += 1;
        applyPagination();
    });

    searchInput?.addEventListener('input', () => {
        state.query = searchInput.value || '';
        state.page = 1;
        applyPagination();
    });

    list.addEventListener('click', (event) => {
        const duplicateButton = event.target.closest('[data-variant-duplicate]');

        if (duplicateButton) {
            const row = duplicateButton.closest('[data-variant-row]');

            if (row) {
                const clone = cloneVariantRow(row);
                row.insertAdjacentElement('afterend', clone);
                reindexRows(clone);
                root.dispatchEvent(new CustomEvent('product-builder:changed'));
                clone.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            return;
        }

        const removeButton = event.target.closest('[data-variant-remove]');

        if (!removeButton || removeButton.disabled) {
            return;
        }

        const row = removeButton.closest('[data-variant-row]');
        const previousRow = row?.previousElementSibling?.matches?.('[data-variant-row]')
            ? row.previousElementSibling
            : row?.nextElementSibling?.matches?.('[data-variant-row]')
                ? row.nextElementSibling
                : null;

        row?.remove();
        reindexRows(previousRow);
        root.dispatchEvent(new CustomEvent('product-builder:changed'));
    });

    list.addEventListener('input', (event) => {
        const row = event.target.closest('[data-variant-row]');

        if (!row) {
            return;
        }

        syncVariantSummary(row, rows().indexOf(row));
        applyPagination(row);
        root.dispatchEvent(new CustomEvent('product-builder:changed'));
    });

    list.addEventListener('change', () => {
        root.dispatchEvent(new CustomEvent('product-builder:changed'));
    });

    reindexRows(rows()[errorIndexes[0]] || null);

    return {
        rows,
        refresh: (focusRow = null) => reindexRows(focusRow),
    };
};

const initImageBuilder = (root, onChange) => {
    const input = root.querySelector('[data-product-image-upload]');
    const browseButton = root.querySelector('[data-product-image-browse]');
    const removeButton = root.querySelector('[data-product-image-remove]');
    const dropzone = root.querySelector('[data-product-image-dropzone]');
    const removeFlag = root.querySelector('[data-product-image-remove-flag]');
    const pathInput = root.querySelector('[data-product-image-path]');
    const statusNode = root.querySelector('[data-product-image-preview-status]');
    const previewImage = root.querySelector('[data-product-image-preview-image]');
    const previewPlaceholder = root.querySelector('[data-product-image-placeholder]');
    const liveImage = root.querySelector('[data-live-preview-image]');
    const liveFallback = root.querySelector('[data-live-preview-fallback]');
    const initialImageUrl = (root.dataset.initialImageUrl || '').trim();
    const initialImageMessage = root.dataset.initialImageMessage || 'Current product image is ready.';
    const initialImageMode = root.dataset.initialImageMode || 'none';
    const initialImagePathValue = (root.dataset.initialImagePathValue || '').trim();
    let objectUrl = null;

    const syncImage = ({ src, hasImage, message }) => {
        if (previewImage) {
            if (hasImage && src) {
                previewImage.src = src;
            } else {
                previewImage.removeAttribute('src');
            }

            setHidden(previewImage, !hasImage);
        }

        if (liveImage) {
            if (hasImage && src) {
                liveImage.src = src;
            } else {
                liveImage.removeAttribute('src');
            }

            setHidden(liveImage, !hasImage);
        }

        setHidden(previewPlaceholder, hasImage);
        setHidden(liveFallback, hasImage);
        dropzone?.classList.toggle('has-image', hasImage);
        setHidden(removeButton, !hasImage && !(pathInput?.value || '').trim() && initialImageMode !== 'fallback');

        if (browseButton) {
            browseButton.textContent = hasImage ? 'Replace image' : 'Upload image';
        }

        if (statusNode) {
            statusNode.textContent = message;
        }
    };

    const currentImageState = () => {
        const file = input?.files?.[0] ?? null;
        const removed = removeFlag?.value === '1';
        const pathValue = pathInput?.value.trim() || '';
        const origin = root.dataset.origin || window.location.origin;

        if (file) {
            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
            }

            objectUrl = URL.createObjectURL(file);

            return {
                hasImage: true,
                src: objectUrl,
                message: `${file.name} is ready to upload as the primary product image.`,
            };
        }

        if (removed) {
            return {
                hasImage: false,
                src: null,
                message: 'Image will be removed when you save this product.',
            };
        }

        if (pathValue) {
            return {
                hasImage: true,
                src: normalizeRelativeUrl(origin, pathValue),
                message: 'Using the advanced image path or URL preview.',
            };
        }

        if (initialImageUrl && (initialImageMode === 'fallback' || pathValue === initialImagePathValue)) {
            return {
                hasImage: true,
                src: initialImageUrl,
                message: initialImageMessage,
            };
        }

        return {
            hasImage: false,
            src: null,
            message: 'No product image yet. Upload one now or keep the branded placeholder until you are ready.',
        };
    };

    const refresh = () => {
        syncImage(currentImageState());
        onChange();
    };

    browseButton?.addEventListener('click', () => input?.click());

    removeButton?.addEventListener('click', () => {
        if (input) {
            input.value = '';
        }

        if (pathInput) {
            pathInput.value = '';
        }

        if (removeFlag) {
            removeFlag.value = '1';
        }

        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }

        refresh();
    });

    input?.addEventListener('change', () => {
        if (removeFlag) {
            removeFlag.value = '0';
        }

        refresh();
    });

    pathInput?.addEventListener('input', () => {
        if (pathInput.value.trim() !== '' && removeFlag) {
            removeFlag.value = '0';
        }

        refresh();
    });

    ['dragenter', 'dragover'].forEach((eventName) => {
        dropzone?.addEventListener(eventName, (event) => {
            event.preventDefault();
            dropzone.classList.add('is-dragging');
        });
    });

    ['dragleave', 'drop'].forEach((eventName) => {
        dropzone?.addEventListener(eventName, (event) => {
            event.preventDefault();
            dropzone.classList.remove('is-dragging');
        });
    });

    dropzone?.addEventListener('drop', (event) => {
        const files = event.dataTransfer?.files;

        if (!files?.length || !input) {
            return;
        }

        const transfer = new DataTransfer();
        transfer.items.add(files[0]);
        input.files = transfer.files;

        if (removeFlag) {
            removeFlag.value = '0';
        }

        refresh();
    });

    const handleBrokenImage = () => {
        syncImage({
            hasImage: false,
            src: null,
            message: 'Preview unavailable for the current image source. Upload a replacement image or verify the stored URL.',
        });
        onChange();
    };

    previewImage?.addEventListener('error', handleBrokenImage);
    liveImage?.addEventListener('error', handleBrokenImage);

    refresh();

    return {
        refresh,
        currentImageState,
    };
};

const initStorefrontPreview = (root, variantBuilder) => {
    const nameInput = root.querySelector('[data-preview-name]');
    const categoryInput = root.querySelector('[data-preview-category]');
    const statusInput = root.querySelector('[data-preview-status]');
    const shortDescription = root.querySelector('[data-preview-short-description]');
    const trackInventory = root.querySelector('[data-preview-track-inventory]');
    const featuredInput = root.querySelector('[data-preview-featured]');
    const forceNewInput = root.querySelector('[data-preview-force-new]');
    const availabilityLabel = root.querySelector('[data-preview-availability-label]');
    const stockSummary = root.querySelector('[data-preview-stock-summary]');
    const stockCopy = root.querySelector('[data-preview-stock-copy]');
    const badgeCopy = root.querySelector('[data-preview-badge-copy]');

    const readVariants = () => {
        return variantBuilder.rows().map((row) => ({
            status: row.querySelector('[data-variant-status]')?.value || 'active',
            price: Number.parseFloat(row.querySelector('[data-variant-price]')?.value || ''),
            comparePrice: Number.parseFloat(row.querySelector('[data-variant-compare-price]')?.value || ''),
            quantity: Number.parseInt(row.querySelector('[data-variant-quantity]')?.value || '0', 10),
            reorderLevel: Number.parseInt(row.querySelector('[data-variant-reorder]')?.value || '0', 10),
            backorder: row.querySelector('[data-variant-backorder]')?.checked || false,
        }));
    };

    const currentPreviewState = () => {
        const allVariants = readVariants();
        const activeVariants = allVariants.filter((variant) => variant.status === 'active');
        const pricedVariants = activeVariants
            .filter((variant) => Number.isFinite(variant.price))
            .sort((left, right) => left.price - right.price);
        const lowestPricedVariant = pricedVariants[0] ?? null;
        const saleVariants = activeVariants.filter((variant) => Number.isFinite(variant.price)
            && Number.isFinite(variant.comparePrice)
            && variant.comparePrice > variant.price);
        const hasPrice = pricedVariants.length > 0;
        const basePrice = lowestPricedVariant?.price ?? null;
        const comparePrice = lowestPricedVariant && Number.isFinite(lowestPricedVariant.comparePrice)
            && lowestPricedVariant.comparePrice > lowestPricedVariant.price
            ? lowestPricedVariant.comparePrice
            : saleVariants[0]?.comparePrice ?? null;
        const sale = saleVariants.length > 0;
        const track = trackInventory?.checked ?? true;
        const quantity = track
            ? activeVariants.reduce((total, variant) => total + (Number.isFinite(variant.quantity) ? variant.quantity : 0), 0)
            : null;
        const reorderLevel = track
            ? activeVariants.reduce((total, variant) => total + (Number.isFinite(variant.reorderLevel) ? variant.reorderLevel : 0), 0)
            : 0;
        const backorder = track ? activeVariants.some((variant) => variant.backorder) : false;
        const status = statusInput?.value || 'active';
        const availabilityState = (() => {
            if (status !== 'active') {
                return 'inactive';
            }

            if (!track) {
                return 'in_stock';
            }

            if ((quantity ?? 0) > 0 && reorderLevel > 0 && (quantity ?? 0) <= reorderLevel) {
                return 'low_stock';
            }

            if ((quantity ?? 0) > 0) {
                return 'in_stock';
            }

            if (backorder) {
                return 'available_for_backorder';
            }

            return 'sold_out';
        })();

        return {
            title: nameInput?.value.trim() || 'Untitled Product',
            category: categoryInput?.selectedOptions?.[0]?.textContent?.trim() || 'Collection',
            description: shortDescription?.value.trim() || 'Add a short description to help your team understand how this product appears on the storefront.',
            price: basePrice,
            comparePrice,
            sale,
            featured: featuredInput?.checked || false,
            isNew: forceNewInput?.checked || root.dataset.initialIsRecent === '1',
            status,
            availabilityState,
            availabilityLabel: labelForAvailability(root, availabilityState),
            quantity: quantity ?? 0,
            reorderLevel,
            trackInventory: track,
            hasPrice,
            hasActiveVariants: activeVariants.length > 0,
            backorder,
        };
    };

    const updateText = (selector, value) => {
        root.querySelectorAll(selector).forEach((node) => {
            node.textContent = value;
        });
    };

    const sync = () => {
        const preview = currentPreviewState();

        updateText('[data-preview-title]', preview.title);
        updateText('[data-preview-category-label]', preview.category);
        updateText('[data-preview-description]', preview.description);
        updateText('[data-preview-status-label]', preview.status.charAt(0).toUpperCase() + preview.status.slice(1));
        updateText('[data-preview-status-copy]', buildStatusCopy(preview.status));

        const formattedPrice = preview.price !== null ? formatPeso(preview.price) : 'Set price';
        const formattedCompare = preview.comparePrice !== null ? formatPeso(preview.comparePrice) : '';
        updateText('[data-preview-price], [data-preview-price-card]', formattedPrice);
        updateText('[data-preview-compare-price], [data-preview-compare-price-card]', formattedCompare);
        toggleNodeGroup(root, '[data-preview-compare-price], [data-preview-compare-price-card]', preview.sale);
        toggleNodeGroup(root, '[data-preview-badge-sale], [data-preview-badge-sale-secondary], [data-preview-badge-sale-card]', preview.sale);
        toggleNodeGroup(root, '[data-preview-badge-new], [data-preview-badge-new-secondary], [data-preview-badge-new-card]', preview.isNew);
        toggleNodeGroup(root, '[data-preview-badge-featured], [data-preview-badge-featured-secondary], [data-preview-badge-featured-card]', preview.featured);

        if (badgeCopy) {
            badgeCopy.textContent = preview.sale
                ? 'SALE is active because an original price is above the selling price on an active variant.'
                : 'SALE will appear automatically when an original price is above the selling price.';
        }

        if (availabilityLabel) {
            availabilityLabel.textContent = preview.availabilityLabel || 'Sold Out';
            availabilityLabel.className = `rounded-full px-3 py-1 text-[0.62rem] font-semibold uppercase tracking-[0.18em] ${availabilityClasses(preview.availabilityState)}`;
        }

        if (stockSummary) {
            stockSummary.textContent = !preview.trackInventory
                ? 'Inventory not tracked'
                : `${preview.quantity} units across active variants`;
        }

        if (stockCopy) {
            stockCopy.textContent = buildStockCopy(preview);
        }
    };

    ['input', 'change'].forEach((eventName) => {
        root.addEventListener(eventName, (event) => {
            if (event.target.closest('[data-product-builder]')) {
                sync();
            }
        });
    });

    root.addEventListener('product-builder:changed', sync);
    sync();
};

export const initProductBuilder = () => {
    document.querySelectorAll('[data-product-builder]').forEach((root) => {
        const variantBuilder = initVariantBuilder(root);

        initImageBuilder(root, () => root.dispatchEvent(new CustomEvent('product-builder:changed')));
        initStorefrontPreview(root, variantBuilder);
        variantBuilder.refresh();
    });
};
