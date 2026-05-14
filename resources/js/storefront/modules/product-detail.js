export const initProductDetailForm = () => {
    const form = document.querySelector('[data-product-form]');

    if (!form) {
        return;
    }

    const availabilityPayload = form.querySelector('[data-product-availability]');
    const hiddenVariant = form.querySelector('input[name="variant_id"]');
    const hiddenColor = form.querySelector('[data-selected-color-input]');
    const hiddenQuantity = form.querySelector('[data-quantity-input]');
    const quantityDisplay = form.querySelector('[data-quantity-display]');
    const addButton = form.querySelector('[data-add-to-cart-button]');
    const availabilityNote = form.querySelector('[data-selected-availability]');
    const headerAvailability = document.querySelector('[data-product-availability-label]');
    const colorContainer = form.querySelector('[data-color-options]');
    const sizeContainer = form.querySelector('[data-size-options]');

    if (!availabilityPayload || !sizeContainer || !colorContainer) {
        return;
    }

    const payload = JSON.parse(availabilityPayload.textContent || '{}');
    const colorOptions = Array.isArray(payload.color_options) ? payload.color_options : [];
    const defaultAvailabilityLabel = payload.default_availability_label || 'Select a size to view availability.';
    const defaultAddToCartLabel = payload.default_add_to_cart_label || 'Select a size';

    let selectedColorKey = hiddenColor?.value || payload.selected_color || colorOptions[0]?.color_key || '';
    let selectedVariantId = hiddenVariant?.value || String(payload.selected_variant_id || '');
    let selectedSize = '';

    const syncButtonState = (option) => {
        const isSelectable = option?.is_selectable === true;
        const isBackorder = option?.backorder_available === true;

        hiddenVariant.value = isSelectable ? String(option?.variant_id || '') : '';

        const availabilityLabel = option?.label || defaultAvailabilityLabel;

        if (availabilityNote) {
            availabilityNote.textContent = availabilityLabel;
        }

        if (headerAvailability) {
            headerAvailability.textContent = availabilityLabel;
        }

        if (!addButton) {
            return;
        }

        addButton.disabled = !isSelectable;
        addButton.textContent = !option
            ? defaultAddToCartLabel
            : !isSelectable
                ? 'Currently unavailable'
                : isBackorder
                    ? 'Preorder now'
                    : 'Add to cart';
    };

    const activeColorOption = () => colorOptions.find((option) => option.color_key === selectedColorKey) || null;

    const renderColorButtons = () => {
        [...colorContainer.querySelectorAll('[data-color-option]')].forEach((button) => {
            const isActive = button.dataset.colorKey === selectedColorKey;
            button.classList.toggle('ys-size-option-active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    };

    const renderSizeButtons = () => {
        const colorOption = activeColorOption();
        const sizeOptions = Array.isArray(colorOption?.size_options) ? colorOption.size_options : [];
        const exactSelected = sizeOptions.find((option) => String(option.variant_id) === String(selectedVariantId)) || null;

        if (exactSelected) {
            selectedSize = exactSelected.size || '';
        }

        const preservedSelection = selectedSize === ''
            ? null
            : sizeOptions.find((option) => option.size === selectedSize && option.is_selectable === true) || null;
        const resolvedSelection = exactSelected || preservedSelection;

        if (!resolvedSelection) {
            selectedVariantId = '';
            if (hiddenVariant) {
                hiddenVariant.value = '';
            }
        } else {
            selectedVariantId = String(resolvedSelection.variant_id);
            selectedSize = resolvedSelection.size || selectedSize;
        }

        sizeContainer.innerHTML = sizeOptions.map((option) => {
            const isSelectable = option.is_selectable === true;
            const isActive = String(option.variant_id) === String(selectedVariantId);
            const classes = [
                'ys-size-option',
                isActive ? 'ys-size-option-active' : '',
                !isSelectable ? 'ys-size-option-unavailable' : '',
            ].filter(Boolean).join(' ');

            return `
                <button
                    type="button"
                    class="${classes}"
                    data-variant-option
                    data-variant-id="${option.variant_id}"
                    data-variant-size="${option.size}"
                    data-variant-color="${option.color}"
                    data-variant-state="${option.state}"
                    data-variant-selectable="${isSelectable ? '1' : '0'}"
                    data-variant-label="${option.label}"
                    data-variant-backorder="${option.backorder_available === true ? '1' : '0'}"
                    ${isSelectable ? 'aria-disabled="false"' : 'disabled aria-disabled="true"'}
                >
                    ${option.size}
                </button>
            `;
        }).join('');

        const variantButtons = [...sizeContainer.querySelectorAll('[data-variant-option]')];

        variantButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const sizeOption = sizeOptions.find((option) => String(option.variant_id) === button.dataset.variantId) || null;

                if (!sizeOption) {
                    syncButtonState(null);
                    return;
                }

                selectedSize = sizeOption.size || '';

                if (sizeOption.is_selectable !== true) {
                    selectedVariantId = '';
                    sizeContainer.querySelectorAll('[data-variant-option]').forEach((optionButton) => {
                        optionButton.classList.remove('ys-size-option-active');
                    });
                    syncButtonState(sizeOption);
                    return;
                }

                selectedVariantId = String(sizeOption.variant_id);
                variantButtons.forEach((optionButton) => optionButton.classList.remove('ys-size-option-active'));
                button.classList.add('ys-size-option-active');
                syncButtonState(sizeOption);
            });
        });

        syncButtonState(resolvedSelection);
    };

    [...colorContainer.querySelectorAll('[data-color-option]')].forEach((button) => {
        button.addEventListener('click', () => {
            selectedColorKey = button.dataset.colorKey || '';

            if (hiddenColor) {
                hiddenColor.value = selectedColorKey;
            }

            renderColorButtons();
            renderSizeButtons();
        });
    });

    renderColorButtons();
    renderSizeButtons();

    form.querySelectorAll('[data-quantity-step]').forEach((button) => {
        button.addEventListener('click', () => {
            const nextValue = Math.max(1, Math.min(10, Number(hiddenQuantity.value) + Number(button.dataset.quantityStep)));
            hiddenQuantity.value = String(nextValue);
            quantityDisplay.textContent = String(nextValue);
        });
    });
};
