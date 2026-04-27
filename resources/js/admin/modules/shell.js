export const initAdminShell = () => {
    const root = document.querySelector('[data-admin-app]');
    const sidebar = document.querySelector('[data-admin-sidebar]');
    const overlay = document.querySelector('[data-admin-overlay]');

    document.querySelector('[data-admin-sidebar-toggle]')?.addEventListener('click', () => {
        root?.toggleAttribute('data-sidebar-open');
    });

    overlay?.addEventListener('click', () => {
        root?.removeAttribute('data-sidebar-open');
    });

    document.querySelectorAll('[data-admin-panel]').forEach((panel, index) => {
        panel.animate(
            [
                { opacity: 0, transform: 'translateY(10px)' },
                { opacity: 1, transform: 'translateY(0)' },
            ],
            {
                duration: 220 + index * 30,
                easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
                fill: 'both',
            },
        );
    });

    sidebar?.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            root?.removeAttribute('data-sidebar-open');
        });
    });
};
