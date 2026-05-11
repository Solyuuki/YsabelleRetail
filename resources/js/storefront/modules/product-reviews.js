const filledStarClasses = ['text-ys-gold', 'border-ys-gold/80', 'bg-ys-gold/10'];
const emptyStarClasses = ['text-ys-ivory/35', 'border-white/12', 'bg-transparent'];

const updateGroup = (group, value) => {
    const buttons = group.querySelectorAll('[data-review-star]');
    const input = group.querySelector('input[type="hidden"]');

    if (input) {
        input.value = String(value);
    }

    buttons.forEach((button) => {
        const active = Number(button.dataset.reviewStar) <= Number(value);

        button.classList.remove(...filledStarClasses, ...emptyStarClasses);
        button.classList.add(...(active ? filledStarClasses : emptyStarClasses));
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
};

const initRatingGroups = () => {
    document.querySelectorAll('[data-review-rating-group]').forEach((group) => {
        const initialValue = Number(group.dataset.currentRating ?? 0);

        updateGroup(group, initialValue);

        group.querySelectorAll('[data-review-star]').forEach((button) => {
            button.addEventListener('click', () => {
                updateGroup(group, Number(button.dataset.reviewStar ?? 0));
            });
        });
    });
};

const initReviewForms = () => {
    document.querySelectorAll('[data-review-form]').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('[data-review-submit]');

            if (!button || button.disabled) {
                return;
            }

            button.disabled = true;
            button.dataset.originalLabel = button.textContent ?? '';
            button.textContent = button.dataset.loadingLabel ?? 'Saving...';
        });
    });

    document.querySelectorAll('[data-review-delete-form]').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('[data-review-delete-submit]');

            if (!button || button.disabled) {
                return;
            }

            button.disabled = true;
            button.dataset.originalLabel = button.textContent ?? '';
            button.textContent = button.dataset.loadingLabel ?? 'Removing...';
        });
    });
};

export const initProductReviews = () => {
    initRatingGroups();
    initReviewForms();
};
