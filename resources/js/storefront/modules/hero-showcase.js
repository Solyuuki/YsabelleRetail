let modelViewerLoader;

const loadModelViewer = () => {
    if (!modelViewerLoader) {
        modelViewerLoader = import('@google/model-viewer');
    }

    return modelViewerLoader;
};

const shouldAutoRotate = () => {
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const isSmallScreen = window.matchMedia('(max-width: 640px)').matches;

    return !prefersReducedMotion && !isSmallScreen;
};

const configureViewerMotion = (viewer) => {
    if (shouldAutoRotate()) {
        viewer.setAttribute('auto-rotate', '');
        viewer.setAttribute('auto-rotate-delay', '0');
        viewer.setAttribute('rotation-per-second', '12deg');
        return;
    }

    viewer.removeAttribute('auto-rotate');
    viewer.removeAttribute('auto-rotate-delay');
    viewer.removeAttribute('rotation-per-second');
};

const activateHeroShowcase = async (root) => {
    if (root.dataset.heroShowcaseReady === 'true') {
        return;
    }

    root.dataset.heroShowcaseReady = 'true';

    const viewer = root.querySelector('[data-hero-model-viewer]');

    if (!viewer) {
        return;
    }

    try {
        await loadModelViewer();

        configureViewerMotion(viewer);

        const modelSrc = root.dataset.modelSrc;
        const variant = root.dataset.modelVariant;

        if (variant) {
            viewer.setAttribute('variant-name', variant);
        }

        viewer.addEventListener(
            'load',
            () => {
                if (variant) {
                    viewer.variantName = variant;
                }

                root.dataset.ready = 'true';
            },
            { once: true }
        );

        viewer.addEventListener(
            'error',
            () => {
                root.dataset.failed = 'true';
            },
            { once: true }
        );

        if (modelSrc) {
            viewer.setAttribute('src', modelSrc);
        }
    } catch (error) {
        root.dataset.failed = 'true';
    }
};

export const initHeroShowcase = () => {
    const showcases = document.querySelectorAll('[data-hero-showcase]');

    if (!showcases.length) {
        return;
    }

    if (!('IntersectionObserver' in window)) {
        showcases.forEach((showcase) => {
            void activateHeroShowcase(showcase);
        });

        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                observer.unobserve(entry.target);
                void activateHeroShowcase(entry.target);
            });
        },
        {
            rootMargin: '240px 0px',
            threshold: 0.1,
        }
    );

    showcases.forEach((showcase) => {
        observer.observe(showcase);
    });
};
