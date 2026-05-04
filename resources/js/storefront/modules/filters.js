const catalogCompactViewport = window.matchMedia('(max-width: 1024px)');

const resolveResponsivePageSize = () => (catalogCompactViewport.matches ? '8' : '12');

const syncResponsivePageSize = (input) => {
    const responsivePageSize = resolveResponsivePageSize();
    const currentUrl = new URL(window.location.href);
    const currentPageSize = currentUrl.searchParams.get(input.name);

    input.value = responsivePageSize;

    if (currentPageSize === responsivePageSize || (currentPageSize === null && responsivePageSize === '12')) {
        return;
    }

    currentUrl.searchParams.set(input.name, responsivePageSize);
    currentUrl.searchParams.delete('page');

    window.location.replace(currentUrl.toString());
};

export const initStorefrontFilters = () => {
    document.querySelectorAll('[data-responsive-per-page]').forEach(syncResponsivePageSize);

    document.querySelectorAll('[data-auto-submit]').forEach((element) => {
        element.addEventListener('change', () => {
            element.form?.requestSubmit();
        });
    });
};
