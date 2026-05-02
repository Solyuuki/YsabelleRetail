const revealFallback = (root) => {
    root.classList.add('is-fallback-visible');

    const fallback = root.querySelector('[data-product-media-fallback]');

    if (fallback) {
        fallback.setAttribute('aria-hidden', 'false');
    }
};

const bindFallback = (root, imageSelector, fallbackSelector) => {
    const image = root.querySelector(imageSelector);
    const fallback = root.querySelector(fallbackSelector);

    if (fallback) {
        fallback.setAttribute('aria-hidden', root.classList.contains('is-fallback-visible') ? 'false' : 'true');
    }

    if (!image) {
        root.classList.add('is-fallback-visible');

        if (fallback) {
            fallback.setAttribute('aria-hidden', 'false');
        }

        return;
    }

    if (image.complete && image.naturalWidth === 0) {
        root.classList.add('is-fallback-visible');

        if (fallback) {
            fallback.setAttribute('aria-hidden', 'false');
        }

        return;
    }

    image.addEventListener(
        'error',
        () => {
            root.classList.add('is-fallback-visible');

            if (fallback) {
                fallback.setAttribute('aria-hidden', 'false');
            }
        },
        { once: true }
    );
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

    document.querySelectorAll('[data-featured-card-media]').forEach((root) => {
        bindFallback(root, '[data-featured-card-image]', '[data-featured-card-fallback]');
    });
};
