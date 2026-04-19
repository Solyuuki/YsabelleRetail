const revealFallback = (root) => {
    root.classList.add('is-fallback-visible');

    const fallback = root.querySelector('[data-product-media-fallback]');

    if (fallback) {
        fallback.setAttribute('aria-hidden', 'false');
    }
};

export const initProductMedia = () => {
    document.querySelectorAll('[data-product-media]').forEach((root) => {
        const image = root.querySelector('[data-product-media-image]');
        const fallback = root.querySelector('[data-product-media-fallback]');

        if (fallback) {
            fallback.setAttribute('aria-hidden', root.classList.contains('is-fallback-visible') ? 'false' : 'true');
        }

        if (!image) {
            revealFallback(root);
            return;
        }

        if (image.complete && image.naturalWidth === 0) {
            revealFallback(root);
            return;
        }

        image.addEventListener(
            'error',
            () => {
                revealFallback(root);
            },
            { once: true }
        );
    });
};
