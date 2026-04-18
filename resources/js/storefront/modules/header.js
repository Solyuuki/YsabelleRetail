export const initHeaderMenus = () => {
    const mobileToggle = document.querySelector('[data-mobile-nav-toggle]');
    const mobilePanel = document.querySelector('[data-mobile-nav-panel]');

    if (mobileToggle && mobilePanel) {
        mobileToggle.addEventListener('click', () => {
            mobilePanel.classList.toggle('hidden');
        });
    }

    const menuRoot = document.querySelector('[data-account-menu]');

    if (!menuRoot) {
        return;
    }

    const trigger = menuRoot.querySelector('[data-account-menu-trigger]');
    const panel = menuRoot.querySelector('[data-account-menu-panel]');

    if (!trigger || !panel) {
        return;
    }

    trigger.addEventListener('click', (event) => {
        event.preventDefault();
        panel.classList.toggle('hidden');
    });

    document.addEventListener('click', (event) => {
        if (!menuRoot.contains(event.target)) {
            panel.classList.add('hidden');
        }
    });
};
