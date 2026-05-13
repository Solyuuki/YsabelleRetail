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
};
