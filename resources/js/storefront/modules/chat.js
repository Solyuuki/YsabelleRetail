export const initChatWidget = () => {
    const root = document.querySelector('[data-chat-shell]');

    if (!root) {
        return;
    }

    const panel = root.querySelector('[data-chat-panel]');

    root.querySelector('[data-chat-toggle]')?.addEventListener('click', () => {
        panel?.classList.toggle('hidden');
    });

    root.querySelector('[data-chat-close]')?.addEventListener('click', () => {
        panel?.classList.add('hidden');
    });
};
