const HIDDEN_PASSWORD_ICON = `
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
        <path d="M3 3l18 18" />
        <path d="M10.6 6.35A11.84 11.84 0 0 1 12 6c6.2 0 10 6 10 6a19.12 19.12 0 0 1-3.12 3.8" />
        <path d="M6.58 6.67C3.92 8.26 2 12 2 12s3.8 6 10 6c1.8 0 3.42-.5 4.83-1.26" />
        <path d="M9.88 9.88A3 3 0 0 0 9 12a3 3 0 0 0 4.24 2.73" />
    </svg>
`;

const VISIBLE_PASSWORD_ICON = `
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
        <path d="M2 12s3.8-6 10-6 10 6 10 6-3.8 6-10 6-10-6-10-6Z" />
        <circle cx="12" cy="12" r="3.2" />
    </svg>
`;

const syncPasswordToggleState = (button, input) => {
    const isVisible = input.type === 'text';
    const icon = button.querySelector('.ys-auth-password-toggle-icon');
    const label = button.querySelector('[data-password-toggle-label]');

    button.setAttribute('data-password-visibility', isVisible ? 'visible' : 'hidden');
    button.setAttribute('aria-label', isVisible ? 'Hide password' : 'Show password');

    if (label) {
        label.textContent = isVisible ? 'Hide password' : 'Show password';
    }

    if (icon) {
        icon.innerHTML = isVisible ? VISIBLE_PASSWORD_ICON : HIDDEN_PASSWORD_ICON;
    }
};

const attachPasswordToggles = () => {
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const inputId = button.getAttribute('aria-controls');

        if (!inputId) {
            return;
        }

        const input = document.getElementById(inputId);

        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        syncPasswordToggleState(button, input);

        button.addEventListener('click', () => {
            input.type = input.type === 'password' ? 'text' : 'password';
            syncPasswordToggleState(button, input);
        });
    });
};

const evaluateStrength = (value) => {
    const hasMinimumLength = value.length >= 8;
    const hasLetter = /[A-Za-z]/.test(value);
    const hasNumber = /\d/.test(value);
    const hasUppercase = /[A-Z]/.test(value);
    const hasLowercase = /[a-z]/.test(value);
    const hasMixedCase = hasUppercase && hasLowercase;
    const hasSymbol = /[^A-Za-z0-9]/.test(value);
    const meetsMinimumRule = hasMinimumLength && hasLetter && hasNumber;
    const isLongerPassword = value.length >= 12;

    if (value.length === 0) {
        return {
            label: 'waiting for input',
            tone: 'idle',
        };
    }

    if (!meetsMinimumRule) {
        return {
            label: 'Weak',
            tone: 'weak',
        };
    }

    if (isLongerPassword && hasMixedCase && hasSymbol) {
        return {
            label: 'Strong',
            tone: 'strong',
        };
    }

    if (isLongerPassword || hasMixedCase) {
        return {
            label: 'Good',
            tone: 'good',
        };
    }

    return {
        label: 'Fair',
        tone: 'fair',
    };
};

const attachStrengthMeters = () => {
    document.querySelectorAll('[data-password-strength]').forEach((meter) => {
        const inputId = meter.getAttribute('data-password-strength-for');

        if (!inputId) {
            return;
        }

        const input = document.getElementById(inputId);
        const copy = meter.querySelector('.ys-auth-strength-copy');

        if (!(input instanceof HTMLInputElement) || !copy) {
            return;
        }

        const syncMeter = () => {
            const strength = evaluateStrength(input.value);
            meter.setAttribute('data-strength', strength.tone);
            copy.textContent = `Strength: ${strength.label}`;
        };

        syncMeter();
        input.addEventListener('input', syncMeter);
    });
};

const attachPasswordMatchFeedback = () => {
    document.querySelectorAll('[data-password-match]').forEach((feedback) => {
        const sourceId = feedback.getAttribute('data-password-match-for');

        if (!sourceId) {
            return;
        }

        const password = document.getElementById(sourceId);
        const confirmation = document.querySelector(`[data-password-confirm-for="${sourceId}"]`);

        if (!(password instanceof HTMLInputElement) || !(confirmation instanceof HTMLInputElement)) {
            return;
        }

        const syncFeedback = () => {
            if (confirmation.value.length === 0) {
                feedback.textContent = 'Re-enter your password to confirm it.';
                feedback.setAttribute('data-match-state', 'idle');
                return;
            }

            if (password.value === confirmation.value) {
                feedback.textContent = 'Passwords match.';
                feedback.setAttribute('data-match-state', 'match');
                return;
            }

            feedback.textContent = 'Passwords do not match yet.';
            feedback.setAttribute('data-match-state', 'mismatch');
        };

        syncFeedback();
        password.addEventListener('input', syncFeedback);
        confirmation.addEventListener('input', syncFeedback);
    });
};

attachPasswordToggles();
attachStrengthMeters();
attachPasswordMatchFeedback();
