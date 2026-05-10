const shouldProtectPage = () => document.body?.dataset.protectedPage === 'true';

const reloadProtectedPage = () => {
    window.location.reload();
};

export const initProtectedPageGuard = () => {
    if (!shouldProtectPage()) {
        return;
    }

    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            reloadProtectedPage();
        }
    });

    const navigationEntry = window.performance?.getEntriesByType?.('navigation')?.[0];

    if (navigationEntry && navigationEntry.type === 'back_forward') {
        reloadProtectedPage();
    }
};
