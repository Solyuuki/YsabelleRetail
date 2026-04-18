export const initStorefrontFilters = () => {
    document.querySelectorAll('[data-auto-submit]').forEach((element) => {
        element.addEventListener('change', () => {
            element.form?.requestSubmit();
        });
    });
};
