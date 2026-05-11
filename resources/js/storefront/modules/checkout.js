const CHECKOUT_DRAFT_FIELDS = [
    'full_name',
    'email',
    'phone',
    'city',
    'address',
    'postal_code',
    'order_notes',
    'payment_method',
];

const CHECKOUT_DRAFT_DEBOUNCE_MS = 900;

const syncDraftStatus = (statusNode, message) => {
    if (!statusNode || !message) {
        return;
    }

    statusNode.textContent = message;
};

const checkoutDraftPayload = (form) => CHECKOUT_DRAFT_FIELDS.reduce((payload, fieldName) => {
    const field = form.elements.namedItem(fieldName);

    if (field instanceof RadioNodeList) {
        payload[fieldName] = field.value ?? '';

        return payload;
    }

    if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement) {
        payload[fieldName] = field.value ?? '';
    }

    return payload;
}, {});

const checkoutDraftSnapshot = (form) => JSON.stringify(checkoutDraftPayload(form));

const queueCheckoutDraftSync = (handler, delay = CHECKOUT_DRAFT_DEBOUNCE_MS) => {
    let timeoutId = null;

    return () => {
        window.clearTimeout(timeoutId);
        timeoutId = window.setTimeout(handler, delay);
    };
};

const initCheckoutDraftRecovery = (form) => {
    const endpoint = form.dataset.checkoutDraftEndpoint;
    const statusNode = document.querySelector('[data-checkout-draft-status]');

    if (!endpoint) {
        return;
    }

    let savedSnapshot = checkoutDraftSnapshot(form);
    let isDirty = false;
    let isSubmitting = false;
    let isSaving = false;

    const markDirtyState = () => {
        isDirty = checkoutDraftSnapshot(form) !== savedSnapshot;
    };

    const beforeUnloadHandler = (event) => {
        if (!isDirty || isSubmitting) {
            return;
        }

        event.preventDefault();
        event.returnValue = '';
    };

    const saveDraft = async () => {
        markDirtyState();

        if (!isDirty || isSubmitting || isSaving) {
            return;
        }

        isSaving = true;
        syncDraftStatus(statusNode, statusNode?.dataset.savingMessage);

        try {
            const response = await window.fetch(endpoint, {
                method: 'PUT',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                },
                body: JSON.stringify(checkoutDraftPayload(form)),
            });

            if (response.ok) {
                savedSnapshot = checkoutDraftSnapshot(form);
                isDirty = false;
                syncDraftStatus(statusNode, statusNode?.dataset.savedMessage);

                return;
            }

            if (response.status === 419) {
                syncDraftStatus(statusNode, statusNode?.dataset.sessionMessage);

                return;
            }

            syncDraftStatus(statusNode, statusNode?.dataset.errorMessage);
        } catch {
            syncDraftStatus(
                statusNode,
                navigator.onLine === false
                    ? statusNode?.dataset.offlineMessage
                    : statusNode?.dataset.errorMessage,
            );
        } finally {
            isSaving = false;
            markDirtyState();
        }
    };

    const scheduleDraftSave = queueCheckoutDraftSync(saveDraft);

    CHECKOUT_DRAFT_FIELDS.forEach((fieldName) => {
        const field = form.elements.namedItem(fieldName);

        if (field instanceof RadioNodeList) {
            Array.from(field).forEach((radio) => {
                radio.addEventListener('change', () => {
                    markDirtyState();
                    scheduleDraftSave();
                });
            });

            return;
        }

        if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement) {
            field.addEventListener('input', () => {
                markDirtyState();
                scheduleDraftSave();
            });
            field.addEventListener('change', () => {
                markDirtyState();
                scheduleDraftSave();
            });
        }
    });

    form.addEventListener('submit', () => {
        isSubmitting = true;
        isDirty = false;
        window.removeEventListener('beforeunload', beforeUnloadHandler);
    });

    window.addEventListener('beforeunload', beforeUnloadHandler);
    window.addEventListener('online', () => {
        if (!isDirty || isSubmitting) {
            return;
        }

        syncDraftStatus(statusNode, statusNode?.dataset.savingMessage);
        scheduleDraftSave();
    });
    window.addEventListener('offline', () => {
        if (!isDirty || isSubmitting) {
            return;
        }

        syncDraftStatus(statusNode, statusNode?.dataset.offlineMessage);
    });
};

export const initCheckoutOptions = () => {
    const form = document.querySelector('[data-checkout-form]');
    const wrapper = document.querySelector('[data-payment-options]');
    const cardSection = document.querySelector('[data-card-payment-section]');
    const submitButton = document.querySelector('[data-checkout-submit]');

    if (form) {
        initCheckoutDraftRecovery(form);
    }

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
