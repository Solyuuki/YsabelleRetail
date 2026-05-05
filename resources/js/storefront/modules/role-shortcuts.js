import { showToast } from './toasts';

const isTypingContext = (target) => {
    if (!(target instanceof HTMLElement)) {
        return false;
    }

    if (target.isContentEditable) {
        return true;
    }

    return Boolean(target.closest('input, textarea, select, button, [contenteditable="true"]'));
};

const readAppAuth = () => {
    if (typeof window.AppAuth !== 'object' || window.AppAuth === null) {
        return null;
    }

    return window.AppAuth;
};

const redirectTo = (url) => {
    if (typeof url === 'string' && url !== '') {
        window.location.assign(url);
    }
};

export const initRoleShortcuts = () => {
    const config = readAppAuth();

    if (!config) {
        return;
    }

    const { routes = {}, messages = {} } = config;
    const isAuthenticated = Boolean(config.isAuthenticated);
    const isAdmin = Boolean(config.isAdmin);
    const isCustomer = Boolean(config.isCustomer);

    document.addEventListener('keydown', (event) => {
        if (!event.ctrlKey || event.altKey || event.metaKey || event.shiftKey || event.repeat) {
            return;
        }

        if (isTypingContext(event.target)) {
            return;
        }

        const key = event.key.toLowerCase();

        if (!['a', 'g', 'u'].includes(key)) {
            return;
        }

        event.preventDefault();

        if (key === 'a') {
            if (isAdmin) {
                redirectTo(routes.adminDashboard);
                return;
            }

            if (!isAuthenticated) {
                redirectTo(routes.adminAccess || routes.login);
                return;
            }

            showToast({
                type: 'error',
                title: 'Admin area unavailable',
                message: messages.adminDenied ?? 'Admin access requires an authorized admin account.',
            });
            return;
        }

        if (key === 'g') {
            if (!isAuthenticated) {
                redirectTo(routes.storefront);
                return;
            }

            showToast({
                type: 'error',
                title: 'Guest shortcut blocked',
                message: messages.guestSignedIn ?? 'You are currently signed in.',
            });

            if (isAdmin) {
                redirectTo(routes.adminDashboard);
                return;
            }

            if (isCustomer) {
                redirectTo(routes.customerDashboard);
                return;
            }

            redirectTo(routes.storefront);
            return;
        }

        if (isCustomer) {
            redirectTo(routes.customerDashboard);
            return;
        }

        if (!isAuthenticated) {
            redirectTo(routes.login);
            return;
        }

        showToast({
            type: 'error',
            title: 'Customer area unavailable',
            message: messages.userDenied ?? 'Customer access requires a signed-in customer account.',
        });
        redirectTo(isAdmin ? routes.adminDashboard : routes.storefront);
    });
};
