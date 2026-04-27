const submitLabel = (button) => {
    return button.dataset.loadingLabel || 'Saving...';
};

export const initAdminForms = () => {
    document.querySelectorAll('form[data-admin-form]').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('[type="submit"]');

            if (!button || button.dataset.submitting === 'true') {
                return;
            }

            button.dataset.submitting = 'true';
            button.dataset.originalLabel = button.innerHTML;
            button.innerHTML = submitLabel(button);
            button.setAttribute('disabled', 'disabled');
        });
    });

    document.querySelectorAll('[data-print-page]').forEach((button) => {
        button.addEventListener('click', () => window.print());
    });

    document.querySelectorAll('[data-variant-add]').forEach((button) => {
        button.addEventListener('click', () => {
            const target = document.querySelector(button.dataset.variantTarget);
            const template = document.querySelector(button.dataset.variantTemplate);

            if (!target || !template) {
                return;
            }

            const index = target.querySelectorAll('[data-variant-row]').length;
            const html = template.innerHTML.replaceAll('__INDEX__', String(index));
            target.insertAdjacentHTML('beforeend', html);
        });
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-variant-remove]');

        if (!button) {
            return;
        }

        button.closest('[data-variant-row]')?.remove();
    });
};
