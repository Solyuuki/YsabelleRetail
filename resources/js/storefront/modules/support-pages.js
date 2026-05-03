const SUPPORT_EMAIL = 'ysabelleretail@gmail.com';
const SUPPORT_PHONE = '09766500867';
const CONTACT_SUCCESS_NOTICE = "Support request sent. We'll reply through your email.";
const CONTACT_ERROR_NOTICE = `We could not send your request. Please try again or email ${SUPPORT_EMAIL}.`;
const CONTACT_CALL_NOTICE = `Trying to open your call app. If nothing opens, call ${SUPPORT_PHONE}.`;
const CONTACT_CALL_COPIED_NOTICE = `Support phone copied. Call ${SUPPORT_PHONE} from your phone.`;
const CONTACT_CALL_FALLBACK_NOTICE = `Call support at ${SUPPORT_PHONE}.`;
const CONTACT_NOTICE_DURATION = 4200;
const MOBILE_DEVICE_PATTERN = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Mobile|Tablet/i;

const sizeGuideVisuals = {
    running: {
        image: '/images/products/running/aurum-runner.jpg',
        title: 'Aurum Runner',
        tag: 'Performance fit',
        copy: 'Balanced cushioning and a secure collar for shoppers who prefer a responsive running fit.',
    },
    casual: {
        image: '/images/products/lifestyle-shoes/maison-drift.jpg',
        title: 'Maison Drift',
        tag: 'Lifestyle fit',
        copy: 'A cleaner daily silhouette with a slightly more forgiving feel through the forefoot.',
    },
    training: {
        image: '/images/products/training-shoes/atlas-flex.jpg',
        title: 'Atlas Flex',
        tag: 'Training fit',
        copy: 'Low-profile stability that works best when the midfoot stays locked without crushing the toes.',
    },
    walking: {
        image: '/images/products/slip-ons/quiet-cove.jpg',
        title: 'Quiet Cove',
        tag: 'Walking fit',
        copy: 'Designed for longer casual wear with a roomier first feel than most performance pairs.',
    },
    basketball: {
        image: '/images/products/basketball-shoes/onyx-vector.jpg',
        title: 'Onyx Vector',
        tag: 'Court fit',
        copy: 'A supportive upper and quick-stop traction profile that usually rewards a little toe-room.',
    },
    boots: {
        image: '/images/products/boots-high-cut/summit-forge.jpg',
        title: 'Summit Forge',
        tag: 'Boot fit',
        copy: 'Structured shaft support with space planning for thicker socks and longer wear sessions.',
    },
};

const sizeGuideRules = {
    running: {
        narrow: { headline: 'True to size', adjustment: 0, copy: 'Your regular size usually keeps the heel secure without overfilling the forefoot.', note: 'Ideal for shoppers who like a locked-in stride.' },
        regular: { headline: 'True to size', adjustment: 0, copy: 'Start with your regular size for a secure performance fit.', note: 'Best for shoppers who like a balanced, close-to-foot feel.' },
        wide: { headline: 'Go half-size up', adjustment: 0.5, copy: 'Extra forefoot space usually makes running pairs more comfortable on longer wear.', note: 'Helpful when toe-room matters more than race-day compression.' },
    },
    casual: {
        narrow: { headline: 'True to size', adjustment: 0, copy: 'Your regular size should keep the profile clean without over-loosening the fit.', note: 'A closer streetwear fit usually works best here.' },
        regular: { headline: 'Relaxed fit', adjustment: 0, copy: 'Stay with your usual size unless you want extra room for thicker socks.', note: 'The upper already feels easier than a performance shoe.' },
        wide: { headline: 'Go half-size up', adjustment: 0.5, copy: 'Half-size up helps if lifestyle pairs usually feel tight across your forefoot.', note: 'A safer starting point for wider feet.' },
    },
    training: {
        narrow: { headline: 'True to size', adjustment: 0, copy: 'Training shoes tend to work best when the midfoot stays neat and stable.', note: 'Good for shoppers who prefer controlled side-to-side hold.' },
        regular: { headline: 'True to size', adjustment: 0, copy: 'Regular sizing is the best starting point for balanced support and stability work.', note: 'Expect a more controlled fit than casual shoes.' },
        wide: { headline: 'Go half-size up', adjustment: 0.5, copy: 'If training pairs usually pinch across the forefoot, half-size up is the safer move.', note: 'Useful when you need room without losing too much lockdown.' },
    },
    walking: {
        narrow: { headline: 'True to size', adjustment: 0, copy: 'Walking pairs already feel easier, so narrow feet usually do well at the regular size.', note: 'Keeps the step-in feel comfortable without slipping.' },
        regular: { headline: 'Relaxed fit', adjustment: 0, copy: 'Your normal size should feel naturally easy for everyday walking and errands.', note: 'Best for shoppers who want low-pressure all-day wear.' },
        wide: { headline: 'Go half-size up', adjustment: 0.5, copy: 'Choose half-size up when you want softer forefoot room for longer casual wear.', note: 'A more forgiving option for wider feet.' },
    },
    basketball: {
        narrow: { headline: 'True to size', adjustment: 0, copy: 'A neat fit helps containment, especially if you already like a close court feel.', note: 'Secure fit matters more than extra volume.' },
        regular: { headline: 'Go half-size up', adjustment: 0.5, copy: 'Many shoppers prefer extra toe-room for repeated stops, cuts, and thicker game socks.', note: 'A common choice for high-movement court use.' },
        wide: { headline: 'Go half-size up', adjustment: 0.5, copy: 'Half-size up is usually the safer starting point for wide feet in court-focused builds.', note: 'Check stock because the extra half-step can matter here.' },
    },
    boots: {
        narrow: { headline: 'True to size', adjustment: 0, copy: 'If you wear lighter socks, your regular size usually stays structured without overfilling the boot.', note: 'Best when you prefer a cleaner, closer fit.' },
        regular: { headline: 'Go half-size up', adjustment: 0.5, copy: 'Boots often feel better with room for thicker socks and longer wear sessions.', note: 'A practical choice for a less restrictive fit.' },
        wide: { headline: 'Go half-size up', adjustment: 0.5, copy: 'Wide feet usually need the extra half-size to avoid pressure in structured uppers.', note: 'Recommended when you want comfort over a tight break-in.' },
    },
};

const buildMailto = (subject, bodyLines) => {
    const query = [
        ['subject', subject],
        ['body', bodyLines.join('\n')],
    ]
        .filter(([, value]) => value)
        .map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(value)}`)
        .join('&');

    return `mailto:${SUPPORT_EMAIL}?${query}`;
};

const isValidEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim());

const matchesMedia = (query) => window.matchMedia?.(query)?.matches ?? false;

const isProbablyMobileDevice = () => {
    if (navigator.userAgentData?.mobile) {
        return true;
    }

    return MOBILE_DEVICE_PATTERN.test(navigator.userAgent ?? '');
};

const shouldUseDirectCallLink = () => {
    const desktopPointer = matchesMedia('(hover: hover) and (pointer: fine)');
    const touchPointer = matchesMedia('(pointer: coarse)') || matchesMedia('(any-pointer: coarse)');

    if (desktopPointer) {
        return false;
    }

    if (touchPointer) {
        return true;
    }

    return isProbablyMobileDevice();
};

const copyTextToClipboard = async (value) => {
    if (navigator.clipboard?.writeText && window.isSecureContext) {
        await navigator.clipboard.writeText(value);
        return true;
    }

    const selection = window.getSelection();
    const previousRange = selection?.rangeCount ? selection.getRangeAt(0) : null;
    const helper = document.createElement('textarea');

    helper.value = value;
    helper.setAttribute('readonly', '');
    helper.style.position = 'fixed';
    helper.style.top = '0';
    helper.style.left = '-9999px';
    helper.style.opacity = '0';
    document.body.appendChild(helper);
    helper.focus();
    helper.select();

    let copied = false;

    try {
        copied = document.execCommand('copy');
    } finally {
        document.body.removeChild(helper);

        if (selection) {
            selection.removeAllRanges();

            if (previousRange) {
                selection.addRange(previousRange);
            }
        }
    }

    if (!copied) {
        throw new Error('Clipboard copy failed.');
    }

    return true;
};

const ensureContactFeedbackStack = () => {
    let stack = document.querySelector('[data-contact-feedback-stack]');

    if (stack) {
        return stack;
    }

    stack = document.createElement('div');
    stack.className = 'pointer-events-none fixed right-4 top-24 z-[72] flex w-full max-w-sm flex-col gap-3 sm:right-6';
    stack.setAttribute('data-contact-feedback-stack', '');
    document.body.appendChild(stack);

    return stack;
};

const showContactFeedback = ({ title = 'Support notice', message, type = 'info' }) => {
    const stack = ensureContactFeedbackStack();
    const toast = document.createElement('div');
    const toneClass = type === 'error' ? 'is-error' : 'is-info';
    const role = type === 'error' ? 'alert' : 'status';

    toast.className = `pointer-events-auto ys-support-feedback-toast ${toneClass}`;
    toast.setAttribute('role', role);
    toast.innerHTML = `
        <div class="flex items-start gap-3">
            <span class="ys-support-feedback-icon" aria-hidden="true">${type === 'error' ? '!' : '&#10003;'}</span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold">${title}</p>
                <p class="mt-1 text-sm leading-6 text-current/82">${message ?? ''}</p>
            </div>
        </div>
    `;

    stack.appendChild(toast);
    window.requestAnimationFrame(() => {
        toast.classList.add('is-visible');
    });

    const dismiss = () => {
        toast.classList.remove('is-visible');
        window.setTimeout(() => toast.remove(), 260);
    };

    window.setTimeout(dismiss, CONTACT_NOTICE_DURATION);
};

const formatSize = (size) => Number.isInteger(size) ? String(size) : size.toFixed(1);

const setActiveButton = (buttons, activeId, keyName) => {
    buttons.forEach((button) => {
        const isActive = button.dataset[keyName] === activeId;
        button.classList.toggle('is-active', isActive);
        button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
};

const initSizeGuide = (root) => {
    const guide = root.querySelector('[data-size-guide]');

    if (!guide) {
        return;
    }

    const sizeButtons = [...guide.querySelectorAll('[data-size-option]')];
    const useCaseButtons = [...guide.querySelectorAll('[data-use-case-option]')];
    const footTypeButtons = [...guide.querySelectorAll('[data-foot-type-option]')];
    const image = root.querySelector('[data-size-guide-image]');
    const tag = root.querySelector('[data-size-guide-tag]');
    const title = root.querySelector('[data-size-guide-title]');
    const caption = root.querySelector('[data-size-guide-caption]');
    const headline = root.querySelector('[data-fit-headline]');
    const recommendation = root.querySelector('[data-fit-recommendation]');
    const suggestedSize = root.querySelector('[data-fit-size]');
    const fitNote = root.querySelector('[data-fit-confidence]');
    const sampleCards = [...root.querySelectorAll('[data-sample-shoe]')];

    let selectedSize = '8';
    let selectedUseCase = 'running';
    let selectedFootType = 'regular';

    const render = () => {
        const visual = sizeGuideVisuals[selectedUseCase];
        const rule = sizeGuideRules[selectedUseCase][selectedFootType];
        const numericSize = Number.parseFloat(selectedSize);
        const nextSize = Math.min(numericSize + rule.adjustment, 12);

        image.src = visual.image;
        image.alt = visual.title;
        tag.textContent = visual.tag;
        title.textContent = visual.title;
        caption.textContent = visual.copy;
        headline.textContent = rule.headline;
        recommendation.textContent = rule.copy;
        suggestedSize.textContent = `Size ${formatSize(nextSize)}`;
        fitNote.textContent = rule.note;

        sampleCards.forEach((card) => {
            card.classList.toggle('is-active', card.dataset.sampleUseCase === selectedUseCase);
        });
    };

    sizeButtons.forEach((button) => {
        button.addEventListener('click', () => {
            selectedSize = button.dataset.sizeValue;
            setActiveButton(sizeButtons, selectedSize, 'sizeValue');
            render();
        });
    });

    useCaseButtons.forEach((button) => {
        button.addEventListener('click', () => {
            selectedUseCase = button.dataset.useCaseValue;
            setActiveButton(useCaseButtons, selectedUseCase, 'useCaseValue');
            render();
        });
    });

    footTypeButtons.forEach((button) => {
        button.addEventListener('click', () => {
            selectedFootType = button.dataset.footTypeValue;
            setActiveButton(footTypeButtons, selectedFootType, 'footTypeValue');
            render();
        });
    });

    render();
};

const initShippingEstimator = (root) => {
    const buttons = [...root.querySelectorAll('[data-shipping-location]')];
    const windowCopy = root.querySelector('[data-shipping-window]');
    const noteCopy = root.querySelector('[data-shipping-note]');

    if (!buttons.length || !windowCopy || !noteCopy) {
        return;
    }

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            buttons.forEach((item) => {
                const isActive = item === button;
                item.classList.toggle('is-active', isActive);
                item.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });

            windowCopy.textContent = button.dataset.locationWindow ?? '';
            noteCopy.textContent = button.dataset.locationNote ?? '';
        });
    });
};

const initReturnsAssistant = (root) => {
    const buttons = [...root.querySelectorAll('[data-returns-action]')];
    const panels = [...root.querySelectorAll('[data-returns-panel]')];
    const title = root.querySelector('[data-returns-title]');
    const summary = root.querySelector('[data-returns-summary]');
    const emailLink = root.querySelector('[data-returns-email-link]');

    if (!buttons.length || !title || !summary) {
        return;
    }

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            const actionId = button.dataset.actionId;

            setActiveButton(buttons, actionId, 'actionId');
            title.textContent = button.dataset.actionTitle ?? '';
            summary.textContent = button.dataset.actionSummary ?? '';

            if (emailLink) {
                emailLink.href = button.dataset.actionMailto ?? emailLink.href;
            }

            panels.forEach((panel) => {
                panel.classList.toggle('hidden', panel.dataset.returnsPanel !== actionId);
            });
        });
    });
};

const initContactHub = (root) => {
    const buttons = [...root.querySelectorAll('[data-contact-issue]')];
    const form = root.querySelector('[data-contact-form]');
    const categoryField = root.querySelector('[data-contact-category]');
    const nameField = root.querySelector('[data-contact-name]');
    const emailField = root.querySelector('[data-contact-email]');
    const referenceField = root.querySelector('[data-contact-reference]');
    const detailsField = root.querySelector('[data-contact-details]');
    const title = root.querySelector('[data-contact-issue-title]');
    const summary = root.querySelector('[data-contact-issue-summary]');
    const referenceLabel = root.querySelector('[data-contact-reference-label]');
    const detailLabel = root.querySelector('[data-contact-detail-label]');
    const emailFallbackLink = root.querySelector('[data-contact-fallback-email-link]');
    const submitButton = root.querySelector('[data-contact-submit-button]');
    const callLinks = [...root.querySelectorAll('[data-contact-call-link]')];

    if (!buttons.length || !form || !categoryField || !referenceField || !detailsField || !nameField || !emailField || !submitButton) {
        return;
    }

    let selectedIssue = categoryField.value || 'order-issue';

    const fieldConfig = [
        {
            key: 'name',
            field: nameField,
            requiredMessage: 'Enter your name before sending the support request.',
        },
        {
            key: 'email',
            field: emailField,
            requiredMessage: 'Enter a reply email before sending the support request.',
            invalidMessage: 'Enter a valid reply email before sending the support request.',
        },
        {
            key: 'details',
            field: detailsField,
            requiredMessage: 'Add issue details before sending the support request.',
            minLength: 10,
            minLengthMessage: 'Add at least 10 characters so support has enough detail.',
        },
    ];

    const fieldErrors = new Map();

    const ensureFieldError = (key, field) => {
        let feedback = fieldErrors.get(key);

        if (feedback) {
            return feedback;
        }

        feedback = document.createElement('p');
        feedback.className = 'ys-support-field-feedback hidden';
        feedback.dataset.contactFieldError = key;
        feedback.id = `contact-${key}-error`;
        feedback.setAttribute('role', 'alert');
        field.closest('.ys-field')?.appendChild(feedback);
        fieldErrors.set(key, feedback);

        return feedback;
    };

    const clearFieldError = (key, field) => {
        const feedback = ensureFieldError(key, field);

        field.classList.remove('is-invalid');
        field.removeAttribute('aria-invalid');
        field.removeAttribute('aria-describedby');
        feedback.textContent = '';
        feedback.classList.add('hidden');
    };

    const setFieldError = (key, field, message) => {
        const feedback = ensureFieldError(key, field);

        field.classList.add('is-invalid');
        field.setAttribute('aria-invalid', 'true');
        field.setAttribute('aria-describedby', feedback.id);
        feedback.textContent = message;
        feedback.classList.remove('hidden');
    };

    const updateMailtoLink = () => {
        if (!emailFallbackLink) {
            return;
        }

        const issueButton = buttons.find((button) => button.dataset.issueId === selectedIssue);
        const issueTitle = issueButton?.dataset.issueLabel ?? 'Support Request';
        const trimmedReference = referenceField.value.trim();
        const bodyLines = [
            'Hello Ysabelle Retail Support,',
            '',
            `Issue type: ${issueTitle}`,
            `Name: ${nameField.value.trim()}`,
            `Reply email: ${emailField.value.trim()}`,
            ...(trimmedReference ? [`${issueButton?.dataset.issueReferenceLabel ?? 'Reference'}: ${trimmedReference}`] : []),
            `${issueButton?.dataset.issueDetailLabel ?? 'Issue details'}: ${detailsField.value.trim()}`,
            '',
            'Thank you.',
        ];

        emailFallbackLink.href = buildMailto(`Support Request: ${issueTitle}`, bodyLines);
    };

    const setSubmitting = (isSubmitting) => {
        submitButton.disabled = isSubmitting;
        submitButton.setAttribute('aria-disabled', isSubmitting ? 'true' : 'false');
        submitButton.textContent = isSubmitting
            ? submitButton.dataset.loadingLabel ?? 'Sending...'
            : submitButton.dataset.idleLabel ?? 'Send Support Request';
    };

    const clearFieldErrors = () => {
        fieldConfig.forEach(({ key, field }) => clearFieldError(key, field));
    };

    const resetForm = () => {
        form.reset();
        categoryField.value = selectedIssue;
        clearFieldErrors();
        updateMailtoLink();
    };

    const payload = () => ({
        category: selectedIssue,
        name: nameField.value.trim(),
        reply_email: emailField.value.trim(),
        reference: referenceField.value.trim(),
        message: detailsField.value.trim(),
        website: form.querySelector('input[name="website"]')?.value ?? '',
    });

    const focusFirstServerError = (errors) => {
        const keyOrder = ['category', 'name', 'reply_email', 'reference', 'message'];
        const fieldMap = {
            name: nameField,
            reply_email: emailField,
            reference: referenceField,
            message: detailsField,
        };

        const firstKey = keyOrder.find((key) => Array.isArray(errors?.[key]) && errors[key].length);

        if (!firstKey) {
            return;
        }

        if (firstKey === 'category') {
            buttons[0]?.focus();
            return;
        }

        fieldMap[firstKey]?.focus();
    };

    const applyServerErrors = (errors) => {
        clearFieldErrors();

        if (Array.isArray(errors?.name) && errors.name[0]) {
            setFieldError('name', nameField, errors.name[0]);
        }

        if (Array.isArray(errors?.reply_email) && errors.reply_email[0]) {
            setFieldError('email', emailField, errors.reply_email[0]);
        }

        if (Array.isArray(errors?.message) && errors.message[0]) {
            setFieldError('details', detailsField, errors.message[0]);
        }

        focusFirstServerError(errors);
    };

    const validateDraftFields = () => {
        let firstInvalidField = null;
        let firstMessage = '';

        fieldConfig.forEach(({ key, field, requiredMessage, invalidMessage, minLength, minLengthMessage }) => {
            const value = field.value.trim();

            clearFieldError(key, field);

            if (!value) {
                setFieldError(key, field, requiredMessage);
                firstInvalidField ??= field;
                firstMessage ||= requiredMessage;
                return;
            }

            if (key === 'email' && !isValidEmail(value)) {
                setFieldError(key, field, invalidMessage);
                firstInvalidField ??= field;
                firstMessage ||= invalidMessage;
                return;
            }

            if (minLength && value.length < minLength) {
                setFieldError(key, field, minLengthMessage);
                firstInvalidField ??= field;
                firstMessage ||= minLengthMessage;
            }
        });

        if (!firstInvalidField) {
            return true;
        }

        firstInvalidField.focus();
        showContactFeedback({
            title: 'Check the required fields',
            message: firstMessage,
            type: 'error',
        });

        return false;
    };

    const render = () => {
        const button = buttons.find((item) => item.dataset.issueId === selectedIssue);

        setActiveButton(buttons, selectedIssue, 'issueId');
        categoryField.value = selectedIssue;
        title.textContent = button?.dataset.issueLabel ?? '';
        summary.textContent = button?.dataset.issueSummary ?? '';
        referenceLabel.textContent = button?.dataset.issueReferenceLabel ?? 'Reference';
        detailLabel.textContent = button?.dataset.issueDetailLabel ?? 'Issue details';
        referenceField.placeholder = button?.dataset.issueReferencePlaceholder ?? '';
        detailsField.placeholder = button?.dataset.issueDetailPlaceholder ?? '';
        updateMailtoLink();
    };

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            selectedIssue = button.dataset.issueId;
            render();
        });
    });

    [nameField, emailField, referenceField, detailsField].forEach((field) => {
        field?.addEventListener('input', () => {
            const matchedField = fieldConfig.find((item) => item.field === field);

            if (matchedField) {
                clearFieldError(matchedField.key, field);
            }

            updateMailtoLink();
        });
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!validateDraftFields()) {
            return;
        }

        setSubmitting(true);

        try {
            const response = await window.fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                },
                body: JSON.stringify(payload()),
            });
            const data = await response.json().catch(() => ({}));

            if (response.status === 422) {
                applyServerErrors(data.errors ?? {});
                showContactFeedback({
                    title: 'Check the required fields',
                    message: Object.values(data.errors ?? {})[0]?.[0] ?? CONTACT_ERROR_NOTICE,
                    type: 'error',
                });
                return;
            }

            if (data.status === 'sent') {
                showContactFeedback({
                    message: data.message ?? CONTACT_SUCCESS_NOTICE,
                });
                resetForm();
                return;
            }

            if (data.status === 'saved_email_failed') {
                showContactFeedback({
                    title: 'Support email unavailable',
                    message: data.message ?? CONTACT_ERROR_NOTICE,
                    type: 'error',
                });
                return;
            }

            showContactFeedback({
                message: data.message ?? CONTACT_ERROR_NOTICE,
                type: 'error',
            });
        } catch {
            showContactFeedback({
                message: CONTACT_ERROR_NOTICE,
                type: 'error',
            });
        } finally {
            setSubmitting(false);
        }
    });

    callLinks.forEach((callLink) => {
        callLink.addEventListener('click', (event) => {
            if (shouldUseDirectCallLink()) {
                showContactFeedback({
                    message: CONTACT_CALL_NOTICE,
                });
                return;
            }

            event.preventDefault();

            copyTextToClipboard(SUPPORT_PHONE)
                .then(() => {
                    showContactFeedback({
                        message: CONTACT_CALL_COPIED_NOTICE,
                    });
                })
                .catch(() => {
                    showContactFeedback({
                        message: CONTACT_CALL_FALLBACK_NOTICE,
                    });
                });
        });
    });

    render();
    setSubmitting(false);
};

export const initSupportPages = () => {
    const root = document.querySelector('[data-support-page]');

    if (!root) {
        return;
    }

    initSizeGuide(root);
    initShippingEstimator(root);
    initReturnsAssistant(root);
    initContactHub(root);
};
