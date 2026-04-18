export const initCheckoutOptions = () => {
    const wrapper = document.querySelector('[data-payment-options]');

    if (!wrapper) {
        return;
    }

    const syncState = () => {
        wrapper.querySelectorAll('.ys-payment-option').forEach((label) => {
            const input = label.querySelector('input[type="radio"]');
            label.classList.toggle('ys-payment-option-active', Boolean(input?.checked));
        });
    };

    wrapper.querySelectorAll('input[type="radio"]').forEach((input) => {
        input.addEventListener('change', syncState);
    });

    syncState();
};
