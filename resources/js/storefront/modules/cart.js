export const initCartQuantityForms = () => {
    document.querySelectorAll('[data-cart-quantity-form]').forEach((form) => {
        const input = form.querySelector('[data-cart-quantity-input]');
        const display = form.querySelector('[data-cart-quantity-display]');

        form.querySelectorAll('[data-cart-step]').forEach((button) => {
            button.addEventListener('click', () => {
                const nextValue = Math.max(0, Math.min(10, Number(input.value) + Number(button.dataset.cartStep)));
                input.value = String(nextValue);
                display.textContent = String(nextValue);
                form.requestSubmit();
            });
        });
    });
};
