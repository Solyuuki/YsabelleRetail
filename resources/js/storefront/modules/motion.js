export const initRevealMotion = () => {
    const elements = document.querySelectorAll('[data-reveal]');

    if (!elements.length) {
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.12 }
    );

    elements.forEach((element) => {
        if (element.dataset.revealDelay) {
            element.style.transitionDelay = `${element.dataset.revealDelay}ms`;
        }

        observer.observe(element);
    });
};
