const MAX_IMAGE_BYTES = 10 * 1024 * 1024;
const ACCEPTED_IMAGE_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];
const ACCEPTED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'];
const DEFAULT_TYPING_LABEL = 'Assistant is checking the catalog...';
const VISUAL_TYPING_LABEL = 'Matching your image against the catalog...';

export const initChatWidget = () => {
    const root = document.querySelector('[data-chat-shell]');

    if (!root) {
        return;
    }

    const panel = root.querySelector('[data-chat-panel]');
    const toggle = root.querySelector('[data-chat-toggle]');
    const closeButton = root.querySelector('[data-chat-close]');
    const minimizeButton = root.querySelector('[data-chat-minimize]');
    const form = root.querySelector('[data-chat-form]');
    const input = root.querySelector('[data-chat-input]');
    const messages = root.querySelector('[data-chat-messages]');
    const typing = root.querySelector('[data-chat-typing]');
    const typingLabel = root.querySelector('[data-chat-typing-label]');
    const promptButtons = Array.from(root.querySelectorAll('[data-chat-prompt]'));
    const visualLaunchers = Array.from(document.querySelectorAll('[data-chat-open-visual]'));
    const visualForm = root.querySelector('[data-visual-search-form]');
    const visualInput = root.querySelector('[data-visual-file-input]');
    const visualTrigger = root.querySelector('[data-visual-file-trigger]');
    const visualClear = root.querySelector('[data-visual-clear]');
    const visualStatus = root.querySelector('[data-visual-status]');
    const visualPreviewImage = root.querySelector('[data-visual-preview-image]');
    const visualFileName = root.querySelector('[data-visual-file-name]');
    const toolDrawer = root.querySelector('[data-chat-tool-drawer]');
    const toolToggle = root.querySelector('[data-chat-tools-toggle]');
    const toolToggleInline = root.querySelector('[data-chat-tools-toggle-inline]');
    const toolClose = root.querySelector('[data-chat-tools-close]');
    const refineMeta = root.querySelector('[data-visual-refine-meta]');
    const refineCount = root.querySelector('[data-visual-filter-count]');
    const refineSummary = root.querySelector('[data-visual-filter-summary]');
    const refineFields = Array.from(root.querySelectorAll('[data-visual-filter-field]'));
    const visualChip = root.querySelector('[data-visual-chip]');
    const visualChipText = root.querySelector('[data-visual-chip-text]');
    const visualRerun = root.querySelector('[data-visual-rerun]');
    const messageEndpoint = root.dataset.messageEndpoint;
    const messageStreamEndpoint = root.dataset.messageStreamEndpoint;
    const visualSearchEndpoint = root.dataset.visualSearchEndpoint;
    const supportsStreaming = Boolean(messageStreamEndpoint && window.ReadableStream && window.TextDecoder);

    let currentPreviewUrl = null;

    const setOpen = (isOpen) => {
        if (!panel) {
            return;
        }

        root.classList.toggle('is-open', isOpen);
        root.dataset.chatState = isOpen ? 'open' : 'closed';
        panel.classList.toggle('is-open', isOpen);
        panel.dataset.open = isOpen ? 'true' : 'false';
        panel.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        panel.style.opacity = isOpen ? '1' : '0';
        panel.style.pointerEvents = isOpen ? 'auto' : 'none';
        panel.style.visibility = isOpen ? 'visible' : 'hidden';
        panel.style.transform = isOpen ? 'translateY(0) scale(1)' : 'translateY(16px) scale(0.98)';
        toggle?.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        toggle?.setAttribute('aria-hidden', isOpen ? 'true' : 'false');

        if (toggle) {
            toggle.tabIndex = isOpen ? -1 : 0;
        }
    };

    const normalizeText = (value) => String(value ?? '')
        .replaceAll('Ã¢â€šÂ±', '₱')
        .replaceAll('â‚±', '₱');

    const escapeHtml = (value) =>
        normalizeText(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    const sessionExpiredMessage = 'Your session expired. Please refresh and try again.';

    const buildRequestHeaders = ({ contentType = null, accept = 'application/json' } = {}) => {
        const headers = {
            Accept: accept,
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        };

        if (contentType) {
            headers['Content-Type'] = contentType;
        }

        return headers;
    };

    const requestErrorMessage = (response, payload, fallbackMessage) => {
        if (response.status === 419) {
            return sessionExpiredMessage;
        }

        const backendMessage = payload?.message ?? firstValidationError(payload);

        if (typeof backendMessage === 'string' && backendMessage.trim() !== '') {
            return backendMessage;
        }

        return fallbackMessage;
    };

    const scrollMessagesToEnd = () => {
        if (!messages) {
            return;
        }

        messages.scrollTop = messages.scrollHeight;
    };

    const toggleTyping = (isVisible, label = DEFAULT_TYPING_LABEL) => {
        if (typingLabel) {
            typingLabel.textContent = label;
        }

        typing?.classList.toggle('hidden', !isVisible);
        typing?.classList.toggle('flex', isVisible);

        if (isVisible) {
            scrollMessagesToEnd();
        }
    };

    const createMessageGroup = (role, variant = 'default') => {
        const wrapper = document.createElement('div');
        wrapper.className = `ys-chat-message-group ${role === 'assistant' ? 'is-assistant' : 'is-user'}`;

        if (variant === 'system') {
            wrapper.classList.add('is-system');
        }

        return wrapper;
    };

    const appendTextBubble = (wrapper, role, answer, variant = 'default') => {
        if (!answer) {
            return;
        }

        const bubble = document.createElement('div');
        bubble.className = `ys-chat-bubble ${role === 'assistant' ? 'is-assistant' : 'is-user'}`;

        if (variant === 'system') {
            bubble.classList.add('is-system');
        }

        bubble.textContent = normalizeText(answer);
        wrapper.appendChild(bubble);
    };

    const renderProductCard = (product) => `
        <article class="ys-assistant-product-card">
            <a href="${escapeHtml(product.url)}" class="ys-assistant-product-link">
                <div class="ys-assistant-product-media">
                    ${
                        product.image_url
                            ? `<img src="${escapeHtml(product.image_url)}" alt="${escapeHtml(product.image_alt)}" class="h-full w-full object-cover">`
                            : `<div class="ys-assistant-product-fallback">${escapeHtml(product.name)}</div>`
                    }
                </div>
                <div class="ys-assistant-product-copy">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="ys-assistant-product-title">${escapeHtml(product.name)}</p>
                            <p class="ys-assistant-product-meta">${escapeHtml(product.category)}</p>
                        </div>
                        <span class="ys-assistant-stock is-${escapeHtml(product.availability.state)}">${escapeHtml(product.availability.label)}</span>
                    </div>
                    <p class="mt-3 text-sm font-semibold text-ys-ivory">${escapeHtml(product.price_label)}</p>
                    ${
                        product.match?.label
                            ? `<p class="ys-assistant-match-label is-${escapeHtml(product.match.confidence)}">${escapeHtml(product.match.label)}${product.match.score_percent ? ` · ${escapeHtml(String(product.match.score_percent))}%` : ''}</p>`
                            : ''
                    }
                    <p class="mt-1 text-xs leading-5 text-ys-ivory/46">${escapeHtml(product.short_description ?? 'Explore this product in the storefront catalog.')}</p>
                    <div class="mt-4 flex items-center justify-between gap-3">
                        <div class="flex flex-wrap gap-2 text-[11px] uppercase tracking-[0.16em] text-ys-ivory/38">
                            ${product.colors?.length ? `<span>${escapeHtml(product.colors.slice(0, 2).join(' / '))}</span>` : ''}
                            ${product.sizes?.length ? `<span>Sizes ${escapeHtml(product.sizes.join(', '))}</span>` : ''}
                        </div>
                        <span class="ys-assistant-product-cta">View item</span>
                    </div>
                </div>
            </a>
        </article>
    `;

    const renderActions = (actions) => {
        if (!actions?.length) {
            return '';
        }

        return `
            <div class="ys-chat-actions">
                ${actions
                    .map((action) => {
                        if (action.type === 'link') {
                            return `<a href="${escapeHtml(action.url)}" class="ys-chat-action">${escapeHtml(action.label)}</a>`;
                        }

                        return `<button type="button" class="ys-chat-action" data-chat-action='${escapeHtml(JSON.stringify(action))}'>${escapeHtml(action.label)}</button>`;
                    })
                    .join('')}
            </div>
        `;
    };

    const appendResponse = (role, payload) => {
        if (!messages) {
            return;
        }

        const variant = payload.variant ?? 'default';
        const wrapper = createMessageGroup(role, variant);
        appendTextBubble(wrapper, role, payload.answer, variant);

        appendResponseDetails(wrapper, role, payload);
        messages.appendChild(wrapper);
        scrollMessagesToEnd();
    };

    const appendResponseDetails = (wrapper, role, payload) => {
        if (!wrapper) {
            return;
        }

        if (role === 'assistant' && payload.products?.length) {
            const products = document.createElement('div');
            products.className = 'ys-chat-product-grid';
            products.innerHTML = payload.products.map(renderProductCard).join('');
            wrapper.appendChild(products);
        }

        if (role === 'assistant' && payload.actions?.length) {
            const actions = document.createElement('div');
            actions.innerHTML = renderActions(payload.actions);
            wrapper.appendChild(actions.firstElementChild);
        }
    };

    const createStreamingAssistantResponse = () => {
        if (!messages) {
            return null;
        }

        const wrapper = createMessageGroup('assistant');
        const bubble = document.createElement('div');
        bubble.className = 'ys-chat-bubble is-assistant';
        wrapper.appendChild(bubble);
        messages.appendChild(wrapper);
        scrollMessagesToEnd();

        return { wrapper, bubble };
    };

    const finalizeStreamingAssistantResponse = (wrapper, bubble, payload) => {
        if (!wrapper || !bubble) {
            return;
        }

        bubble.textContent = normalizeText(payload.answer ?? bubble.textContent ?? '');
        appendResponseDetails(wrapper, 'assistant', payload);
        scrollMessagesToEnd();
    };

    const appendVisualUploadMessage = (file) => {
        if (!messages || !currentPreviewUrl) {
            return;
        }

        const wrapper = createMessageGroup('user');
        const bubble = document.createElement('div');
        bubble.className = 'ys-chat-image-bubble is-user';
        bubble.innerHTML = `
            <img src="${escapeHtml(currentPreviewUrl)}" alt="${escapeHtml(file.name)}" class="ys-chat-image-preview">
            <div class="ys-chat-image-copy">
                <p class="ys-chat-image-title">Image uploaded</p>
                <p class="ys-chat-image-caption">${escapeHtml(file.name)}</p>
            </div>
        `;

        wrapper.appendChild(bubble);
        messages.appendChild(wrapper);
        scrollMessagesToEnd();
    };

    const resetPreview = () => {
        if (currentPreviewUrl) {
            URL.revokeObjectURL(currentPreviewUrl);
            currentPreviewUrl = null;
        }

        if (visualInput) {
            visualInput.value = '';
        }

        if (visualPreviewImage) {
            visualPreviewImage.src = '';
        }

        if (visualFileName) {
            visualFileName.textContent = '';
        }

        visualStatus?.classList.add('hidden');
        visualStatus?.classList.remove('grid');
        setToolDrawerOpen(false);
        syncRefineSummary();
    };

    const setPreview = (file) => {
        if (!visualPreviewImage || !visualFileName) {
            return;
        }

        if (currentPreviewUrl) {
            URL.revokeObjectURL(currentPreviewUrl);
        }

        currentPreviewUrl = URL.createObjectURL(file);
        visualPreviewImage.src = currentPreviewUrl;
        visualFileName.textContent = file.name;
        visualStatus?.classList.remove('hidden');
        visualStatus?.classList.add('grid');
    };

    const setToolDrawerOpen = (isOpen) => {
        if (!toolDrawer) {
            return;
        }

        toolDrawer.classList.toggle('hidden', !isOpen);
        toolDrawer.classList.toggle('grid', isOpen);
        toolDrawer.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        toolToggle?.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        toolToggleInline?.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        root.classList.toggle('is-tools-open', isOpen);
    };

    const toggleToolDrawer = (nextState = null) => {
        const shouldOpen = typeof nextState === 'boolean'
            ? nextState
            : toolDrawer?.classList.contains('hidden');

        setToolDrawerOpen(Boolean(shouldOpen));
    };

    const activeRefineFilters = () => refineFields
        .map((field) => {
            const value = String(field.value ?? '').trim();

            if (!value) {
                return null;
            }

            const label = field.dataset.filterLabel ?? field.name;
            const displayValue = field instanceof HTMLSelectElement
                ? field.options[field.selectedIndex]?.text ?? value
                : value;

            return { label, value: displayValue };
        })
        .filter(Boolean);

    const syncRefineSummary = () => {
        const hasUpload = Boolean(visualInput?.files?.[0]);
        const filters = activeRefineFilters();
        const filterCount = filters.length;

        root.classList.toggle('has-visual-upload', hasUpload);

        if (refineCount) {
            refineCount.textContent = `${filterCount} filter${filterCount === 1 ? '' : 's'}`;
        }

        if (refineMeta) {
            refineMeta.textContent = hasUpload
                ? (filterCount
                    ? 'Your uploaded image is ready with optional narrowing filters applied.'
                    : 'Your uploaded image is ready. Add optional filters only if you want a narrower match.')
                : 'Keep optional filters tucked away until you want a narrower match.';
        }

        if (visualChip && visualChipText) {
            const chipSegments = [];

            if (visualInput?.files?.[0]?.name) {
                chipSegments.push(visualInput.files[0].name);
            }

            if (filterCount > 0) {
                chipSegments.push(`${filterCount} filter${filterCount === 1 ? '' : 's'}`);
            }

            visualChipText.textContent = chipSegments.join(' | ');
            visualChip.classList.toggle('hidden', !hasUpload);
            visualChip.classList.toggle('flex', hasUpload);
        }

        if (refineSummary) {
            const chips = [];

            if (hasUpload) {
                chips.push('<span class="ys-chat-refine-tag is-highlight">Image ready</span>');
            }

            filters.forEach((filter) => {
                chips.push(`<span class="ys-chat-refine-tag">${escapeHtml(filter.label)}: ${escapeHtml(filter.value)}</span>`);
            });

            refineSummary.innerHTML = chips.join('');
            refineSummary.classList.toggle('hidden', chips.length === 0);
            refineSummary.classList.toggle('flex', chips.length > 0);
        }

        if (visualRerun) {
            visualRerun.disabled = !hasUpload;
        }
    };

    const validateImage = (file) => {
        if (!file) {
            return 'Select an image first to use Visual Search.';
        }

        const extension = String(file.name ?? '')
            .split('.')
            .pop()
            ?.toLowerCase();
        const isImageMime = !file.type || file.type.startsWith('image/');

        if (!isImageMime && !ACCEPTED_IMAGE_TYPES.includes(file.type) && !ACCEPTED_IMAGE_EXTENSIONS.includes(extension)) {
            return 'Please upload a JPG, PNG, WEBP, or HEIC image.';
        }

        if (file.size > MAX_IMAGE_BYTES) {
            return 'Please use an image smaller than 10 MB.';
        }

        return null;
    };

    const postMessageJson = async (message) => {
        const response = await fetch(messageEndpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: buildRequestHeaders({
                contentType: 'application/json',
                accept: 'application/json',
            }),
            body: JSON.stringify({ message }),
        });

        const payload = await safeJson(response);

        if (!response.ok) {
            throw new Error(requestErrorMessage(response, payload, 'The assistant could not process that request.'));
        }

        return payload ?? {};
    };

    const streamMessage = async (message) => {
        if (!messageStreamEndpoint) {
            throw new Error('Streaming is unavailable.');
        }

        const streamState = createStreamingAssistantResponse();

        if (!streamState) {
            throw new Error('The assistant thread is unavailable.');
        }

        const { wrapper, bubble } = streamState;
        let accumulated = '';
        let finalized = false;

        try {
            const response = await fetch(messageStreamEndpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: buildRequestHeaders({
                    contentType: 'application/json',
                    accept: 'text/event-stream',
                }),
                body: JSON.stringify({ message }),
            });

            if (!response.ok || !response.body) {
                const fallbackPayload = await safeJson(response);
                throw new Error(requestErrorMessage(response, fallbackPayload, 'The assistant could not process that request.'));
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';

            const processChunk = (eventChunk) => {
                const parsed = parseSseEvent(eventChunk);

                if (!parsed) {
                    return;
                }

                if (parsed.event === 'chunk') {
                    accumulated += String(parsed.data?.text ?? '');
                    bubble.textContent = normalizeText(accumulated);
                    scrollMessagesToEnd();
                    toggleTyping(false);
                    return;
                }

                if (parsed.event === 'done') {
                    finalized = true;
                    finalizeStreamingAssistantResponse(wrapper, bubble, parsed.data ?? { answer: accumulated });
                    return;
                }

                if (parsed.event === 'error') {
                    throw new Error(parsed.data?.message ?? 'The assistant is temporarily unavailable. Please try again.');
                }
            };

            while (true) {
                const { value, done } = await reader.read();

                if (done) {
                    break;
                }

                buffer += decoder.decode(value, { stream: true });

                const events = buffer.split(/\r?\n\r?\n/);
                buffer = events.pop() ?? '';
                events.forEach(processChunk);
            }

            buffer += decoder.decode();

            if (buffer.trim() !== '') {
                processChunk(buffer);
            }

            if (!finalized) {
                finalizeStreamingAssistantResponse(wrapper, bubble, { answer: accumulated });
            }
        } catch (error) {
            wrapper.remove();
            throw error;
        }
    };

    const sendMessage = async (message) => {
        const trimmed = message.trim();

        if (!trimmed || !messageEndpoint) {
            appendResponse('assistant', {
                answer: 'Please type a question or tap one of the quick prompts so I can help.',
            });
            return;
        }

        appendResponse('user', { answer: trimmed });

        if (input) {
            input.value = '';
        }

        toggleTyping(true, DEFAULT_TYPING_LABEL);

        try {
            const startedAt = Date.now();
            if (supportsStreaming) {
                await streamMessage(trimmed);
            } else {
                const payload = await postMessageJson(trimmed);
                const elapsed = Date.now() - startedAt;

                if (elapsed < 500) {
                    await wait(500 - elapsed);
                }

                appendResponse('assistant', payload);
            }
        } catch (error) {
            appendResponse('assistant', {
                answer: error instanceof Error ? error.message : 'The assistant is temporarily unavailable. Please try again.',
                variant: 'system',
            });
        } finally {
            toggleTyping(false);
        }
    };

    const submitVisualSearch = async () => {
        const file = visualInput?.files?.[0];

        if (!visualForm || !file || !visualSearchEndpoint) {
            appendResponse('assistant', {
                answer: 'Select an image first to use Visual Search.',
                variant: 'system',
            });
            return;
        }

        const clientError = validateImage(file);

        if (clientError) {
            appendResponse('assistant', { answer: clientError, variant: 'system' });
            return;
        }

        const formData = new FormData(visualForm);
        toggleTyping(true, VISUAL_TYPING_LABEL);

        try {
            const startedAt = Date.now();
            const response = await fetch(visualSearchEndpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: buildRequestHeaders(),
                body: formData,
            });

            const payload = await safeJson(response);

            if (!response.ok) {
                throw new Error(requestErrorMessage(response, payload, 'Visual Search could not process that image.'));
            }

            const elapsed = Date.now() - startedAt;
            if (elapsed < 500) {
                await wait(500 - elapsed);
            }

            appendResponse('assistant', payload ?? {});
            setToolDrawerOpen(false);
        } catch (error) {
            appendResponse('assistant', {
                answer: error instanceof Error ? error.message : 'Visual Search is temporarily unavailable. Please try again.',
                variant: 'system',
            });
        } finally {
            toggleTyping(false);
        }
    };

    const handleAction = (action) => {
        if (!action) {
            return;
        }

        if (action.type === 'message' && action.message) {
            sendMessage(action.message);
            return;
        }

        if (action.type === 'panel' && action.target === 'visual-search') {
            setOpen(true);
            setToolDrawerOpen(true);
            visualInput?.click();
        }
    };

    toggle?.addEventListener('click', () => {
        const isOpen = panel?.classList.contains('is-open');
        setOpen(!isOpen);

        if (!isOpen) {
            input?.focus();
        } else {
            setToolDrawerOpen(false);
        }
    });

    closeButton?.addEventListener('click', () => {
        setOpen(false);
        setToolDrawerOpen(false);
    });

    minimizeButton?.addEventListener('click', () => {
        setOpen(false);
        setToolDrawerOpen(false);
    });

    promptButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const prompt = button.dataset.chatPrompt ?? '';
            setOpen(true);

            if (prompt.toLowerCase().includes('image')) {
                setToolDrawerOpen(true);
                visualInput?.click();
                return;
            }

            sendMessage(prompt);
        });
    });

    visualLaunchers.forEach((button) => {
        button.addEventListener('click', () => {
            setOpen(true);
            setToolDrawerOpen(true);
            visualInput?.click();
        });
    });

    root.addEventListener('click', (event) => {
        const actionButton = event.target instanceof HTMLElement ? event.target.closest('[data-chat-action]') : null;

        if (!actionButton) {
            return;
        }

        const action = actionButton.getAttribute('data-chat-action');

        if (!action) {
            return;
        }

        handleAction(JSON.parse(action));
    });

    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        sendMessage(input?.value ?? '');
    });

    input?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        sendMessage(input.value);
    });

    visualTrigger?.addEventListener('click', () => {
        setOpen(true);
        setToolDrawerOpen(true);
        visualInput?.click();
    });

    toolToggle?.addEventListener('click', () => {
        setOpen(true);
        toggleToolDrawer();
    });

    toolToggleInline?.addEventListener('click', () => {
        setOpen(true);
        setToolDrawerOpen(true);
    });

    toolClose?.addEventListener('click', () => {
        setToolDrawerOpen(false);
    });

    visualClear?.addEventListener('click', () => {
        resetPreview();
        appendResponse('assistant', {
            answer: 'The uploaded image was removed. Add another image whenever you want to search again.',
        });
    });

    visualRerun?.addEventListener('click', () => {
        if (!visualInput?.files?.[0]) {
            appendResponse('assistant', {
                answer: 'Upload an image first before refining results.',
            });
            return;
        }

        submitVisualSearch();
    });

    visualInput?.addEventListener('change', () => {
        const file = visualInput.files?.[0];
        const clientError = validateImage(file);

        if (clientError) {
            resetPreview();
            appendResponse('assistant', { answer: clientError, variant: 'system' });
            return;
        }

        if (!file) {
            return;
        }

        setOpen(true);
        setPreview(file);
        setToolDrawerOpen(true);
        syncRefineSummary();
        appendVisualUploadMessage(file);
        submitVisualSearch();
    });

    refineFields.forEach((field) => {
        field.addEventListener(field instanceof HTMLSelectElement ? 'change' : 'input', syncRefineSummary);
    });

    setToolDrawerOpen(false);
    syncRefineSummary();

    setOpen(false);
};

const wait = (ms) => new Promise((resolve) => window.setTimeout(resolve, ms));

const firstValidationError = (payload) => {
    const errors = payload?.errors;

    if (!errors || typeof errors !== 'object') {
        return null;
    }

    const firstError = Object.values(errors)[0];

    return Array.isArray(firstError) ? firstError[0] : null;
};

const safeJson = async (response) => {
    try {
        return await response.json();
    } catch {
        return null;
    }
};

const parseSseEvent = (chunk) => {
    const lines = chunk.split(/\r?\n/);
    let event = 'message';
    const dataLines = [];

    lines.forEach((line) => {
        if (line.startsWith('event:')) {
            event = line.slice(6).trim();
            return;
        }

        if (line.startsWith('data:')) {
            dataLines.push(line.slice(5).trimStart());
        }
    });

    if (!dataLines.length) {
        return null;
    }

    const rawData = dataLines.join('\n');

    try {
        return {
            event,
            data: JSON.parse(rawData),
        };
    } catch {
        return {
            event,
            data: { text: rawData },
        };
    }
};
