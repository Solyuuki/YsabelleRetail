const createToastMarkup = (toast) => {
    const wrapper = document.createElement('div');
    const isError = (toast.type ?? 'success') === 'error';

    wrapper.className = `pointer-events-auto ys-toast ${isError ? 'ys-toast-error' : 'ys-toast-success'}`;
    wrapper.setAttribute('data-toast', '');
    wrapper.innerHTML = `
        <div class="flex items-start gap-3">
            <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-current/20">
                <span class="text-sm leading-none">${isError ? '!' : '&#10003;'}</span>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold">${toast.title ?? 'Notice'}</p>
                <p class="mt-1 text-sm text-current/80">${toast.message ?? ''}</p>
            </div>
            <button type="button" class="shrink-0 text-current/70 transition hover:text-current" data-toast-dismiss aria-label="Dismiss toast">&times;</button>
        </div>
    `;

    return wrapper;
};

const wireToast = (toast, index = 0) => {
    const dismiss = () => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-8px)';
        window.setTimeout(() => toast.remove(), 220);
    };

    toast.querySelector('[data-toast-dismiss]')?.addEventListener('click', dismiss);
    window.setTimeout(dismiss, 4200 + index * 400);
};

const ensureToastStack = () => {
    let stack = document.querySelector('[data-toast-stack]');

    if (stack) {
        return stack;
    }

    stack = document.createElement('div');
    stack.className = 'pointer-events-none fixed right-4 top-24 z-[70] flex w-full max-w-sm flex-col gap-3 sm:right-6';
    stack.setAttribute('data-toast-stack', '');
    document.body.appendChild(stack);

    return stack;
};

export const initToasts = () => {
    document.querySelectorAll('[data-toast]').forEach((toast, index) => {
        wireToast(toast, index);
    });
};

export const showToast = (toast) => {
    const stack = ensureToastStack();
    const nextToast = createToastMarkup(toast);
    stack.appendChild(nextToast);
    wireToast(nextToast);
};
