const loadingLabel = (button) => {
    return button.dataset.loadingLabel || 'Working...';
};

export const initSessionExitForms = () => {
    document.querySelectorAll('form[data-session-exit-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const button = form.querySelector('[type="submit"]');

            if (!button) {
                return;
            }

            if (form.dataset.submitting === 'true' || button.dataset.submitting === 'true') {
                event.preventDefault();

                return;
            }

            form.dataset.submitting = 'true';
            button.dataset.submitting = 'true';
            button.dataset.originalLabel = button.innerHTML;
            button.innerHTML = loadingLabel(button);
            button.setAttribute('disabled', 'disabled');
        });
    });
};
