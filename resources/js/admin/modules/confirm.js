export const initConfirmActions = () => {
    document.addEventListener('submit', (event) => {
        const form = event.target.closest('form[data-confirm-message]');

        if (!form) {
            return;
        }

        const confirmed = window.confirm(form.dataset.confirmMessage || 'Are you sure?');

        if (!confirmed) {
            event.preventDefault();
        }
    });
};
