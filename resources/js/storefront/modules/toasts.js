export const initToasts = () => {
    document.querySelectorAll('[data-toast]').forEach((toast, index) => {
        const dismiss = () => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-8px)';
            window.setTimeout(() => toast.remove(), 220);
        };

        toast.querySelector('[data-toast-dismiss]')?.addEventListener('click', dismiss);
        window.setTimeout(dismiss, 4200 + index * 400);
    });
};
