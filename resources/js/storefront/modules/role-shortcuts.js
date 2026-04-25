import { showToast } from './toasts';

const isTypingContext = (target) => {
    if (!(target instanceof HTMLElement)) {
        return false;
    }

    if (target.isContentEditable) {
        return true;
    }

    return Boolean(target.closest('input, textarea, select, [contenteditable="true"]'));
};

const readShortcutConfig = () => {
    const node = document.getElementById('ys-role-shortcuts-config');

    if (!node?.textContent) {
        return null;
    }

    try {
        return JSON.parse(node.textContent);
    } catch {
        return null;
    }
};

const redirectTo = (url) => {
    if (typeof url === 'string' && url !== '') {
        window.location.assign(url);
    }
};

export const initRoleShortcuts = () => {
    const config = readShortcutConfig();

    if (!config) {
        return;
    }

    const { routes = {}, user = {}, messages = {} } = config;
    const isAuthenticated = Boolean(user.authenticated);
    const isAdmin = Boolean(user.admin);
    const isCustomer = Boolean(user.customer);

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
                redirectTo(routes.admin);
                return;
            }

            if (!isAuthenticated) {
                redirectTo(routes.admin);
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
                redirectTo(routes.guest);
                return;
            }

            if (isAdmin) {
                showToast({
                    type: 'error',
                    title: 'Guest shortcut blocked',
                    message: messages.guestDenied ?? 'Guest mode does not sign you out. Returning to your active area instead.',
                });
                redirectTo(routes.admin);
                return;
            }

            showToast({
                type: 'error',
                title: 'Guest shortcut blocked',
                message: messages.guestDenied ?? 'Guest mode does not sign you out. Returning to your active area instead.',
            });
            redirectTo(routes.user);
            return;
        }

        if (isCustomer) {
            redirectTo(routes.user);
            return;
        }

        if (!isAuthenticated) {
            redirectTo(routes.user);
            return;
        }

        showToast({
            type: 'error',
            title: 'Customer area unavailable',
            message: messages.userDenied ?? 'Customer access requires a signed-in customer account.',
        });
        redirectTo(isAdmin ? routes.admin : routes.guest);
    });
};
