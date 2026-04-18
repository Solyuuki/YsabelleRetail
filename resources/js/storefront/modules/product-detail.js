export const initProductDetailForm = () => {
    const form = document.querySelector('[data-product-form]');

    if (!form) {
        return;
    }

    const hiddenVariant = form.querySelector('input[name="variant_id"]');
    const hiddenQuantity = form.querySelector('[data-quantity-input]');
    const quantityDisplay = form.querySelector('[data-quantity-display]');
    const addButton = form.querySelector('[data-add-to-cart-button]');

    form.querySelectorAll('[data-variant-option]').forEach((button) => {
        button.addEventListener('click', () => {
            form.querySelectorAll('[data-variant-option]').forEach((option) => option.classList.remove('ys-size-option-active'));
            button.classList.add('ys-size-option-active');
            hiddenVariant.value = button.dataset.variantId ?? '';
            addButton.textContent = 'Add to cart';
        });
    });

    form.querySelectorAll('[data-quantity-step]').forEach((button) => {
        button.addEventListener('click', () => {
            const nextValue = Math.max(1, Math.min(10, Number(hiddenQuantity.value) + Number(button.dataset.quantityStep)));
            hiddenQuantity.value = String(nextValue);
            quantityDisplay.textContent = String(nextValue);
        });
    });
};
