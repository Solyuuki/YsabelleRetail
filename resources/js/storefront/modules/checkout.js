export const initCheckoutOptions = () => {
    const wrapper = document.querySelector('[data-payment-options]');
    const cardSection = document.querySelector('[data-card-payment-section]');
    const submitButton = document.querySelector('[data-checkout-submit]');

    if (!wrapper) {
        return;
    }

    const paymentInputs = Array.from(wrapper.querySelectorAll('input[type="radio"][name="payment_method"]'));
    const cardInputs = Array.from(cardSection?.querySelectorAll('input, select, textarea') ?? []);

    const selectedMethod = () => paymentInputs.find((input) => input.checked)?.value ?? 'cod';

    const syncState = () => {
        wrapper.querySelectorAll('.ys-payment-option').forEach((label) => {
            const input = label.querySelector('input[type="radio"]');
            label.classList.toggle('ys-payment-option-active', Boolean(input?.checked));
        });

        const usesSimulatedCard = selectedMethod() === 'card_simulated';

        if (cardSection) {
            cardSection.classList.toggle('hidden', !usesSimulatedCard);
            cardSection.toggleAttribute('hidden', !usesSimulatedCard);
            cardSection.setAttribute('aria-hidden', usesSimulatedCard ? 'false' : 'true');
        }

        cardInputs.forEach((input) => {
            input.toggleAttribute('disabled', !usesSimulatedCard);
        });

        if (submitButton) {
            const nextLabel = usesSimulatedCard
                ? submitButton.dataset.cardLabel
                : submitButton.dataset.defaultLabel;
            const totalLabel = submitButton.dataset.totalLabel;

            submitButton.innerHTML = `${nextLabel} &middot; ${totalLabel}`;
        }
    };

    paymentInputs.forEach((input) => {
        input.addEventListener('change', syncState);
    });

    syncState();
};
