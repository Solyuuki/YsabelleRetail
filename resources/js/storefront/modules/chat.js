const MAX_IMAGE_BYTES = 10 * 1024 * 1024;
const ACCEPTED_IMAGE_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
const ACCEPTED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
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
    const visualStatusCopy = root.querySelector('[data-visual-status-copy]');
    const visualStateChip = root.querySelector('[data-visual-state-chip]');
    const visualRetry = root.querySelector('[data-visual-retry]');
    const toolDrawer = root.querySelector('[data-chat-tool-drawer]');
    const toolToggle = root.querySelector('[data-chat-tools-toggle]');
    const toolToggleInline = root.querySelector('[data-chat-tools-toggle-inline]');
    const toolClose = root.querySelector('[data-chat-tools-close]');
    const refineMeta = root.querySelector('[data-visual-refine-meta]');
    const refineCount = root.querySelector('[data-visual-filter-count]');
    const refineSummary = root.querySelector('[data-visual-filter-summary]');
    const refineFields = Array.from(root.querySelectorAll('[data-visual-filter-field]'));
    const visualChip = root.querySelector('[data-visual-chip]');
    const visualChipBadge = root.querySelector('[data-visual-chip-badge]');
    const visualChipText = root.querySelector('[data-visual-chip-text]');
    const visualChipRetry = root.querySelector('[data-visual-chip-retry]');
    const visualRerun = root.querySelector('[data-visual-rerun]');
    const sendButton = root.querySelector('[data-chat-send]');
    const sendButtonIcon = root.querySelector('[data-chat-send-icon]');
    const sendButtonSpinner = root.querySelector('[data-chat-send-spinner]');
    const sendButtonLabel = root.querySelector('[data-chat-send-label]');
    const messageEndpoint = root.dataset.messageEndpoint;
    const messageStreamEndpoint = root.dataset.messageStreamEndpoint;
    const visualSearchEndpoint = root.dataset.visualSearchEndpoint;
    const supportsStreaming = Boolean(messageStreamEndpoint && window.ReadableStream && window.TextDecoder);

    let currentPreviewUrl = null;
    let selectedVisualFile = null;
    let visualRequestId = 0;
    let visualSelectionId = 0;
    let activeVisualRequest = null;
    let visualUiState = 'idle';
    let activeComposerRequest = null;
    let lastVisualSentSelectionId = 0;
    let pendingAssistantRetry = null;
    let csrfRefreshPromise = null;
    let visualThreadState = null;

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

    const setCsrfToken = (token) => {
        const meta = document.querySelector('meta[name="csrf-token"]');

        if (!meta || typeof token !== 'string' || token.trim() === '') {
            return;
        }

        meta.setAttribute('content', token.trim());
    };

    const sessionExpiredRetryMessage = 'Session expired. Tap retry.';
    const sessionExpiredReloadMessage = 'Session expired. Reload to continue.';

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

    const createAssistantError = (message, details = {}) => Object.assign(new Error(message), details);

    const buildSessionExpiredPayload = ({ canRetry }) => ({
        answer: canRetry ? sessionExpiredRetryMessage : sessionExpiredReloadMessage,
        variant: 'system',
        actions: canRetry
            ? [{ label: 'Retry', type: 'assistant-retry' }]
            : [{ label: 'Reload', type: 'assistant-reload' }],
    });

    const refreshCsrfToken = async () => {
        if (csrfRefreshPromise) {
            return csrfRefreshPromise;
        }

        csrfRefreshPromise = (async () => {
            try {
                const response = await fetch(window.location.href, {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        Accept: 'text/html,application/xhtml+xml',
                    },
                });

                if (!response.ok) {
                    return '';
                }

                const html = await response.text();
                const documentFragment = new DOMParser().parseFromString(html, 'text/html');
                const refreshedToken = documentFragment.querySelector('meta[name="csrf-token"]')?.getAttribute('content')?.trim() ?? '';

                if (refreshedToken) {
                    setCsrfToken(refreshedToken);
                }

                return refreshedToken;
            } catch {
                return '';
            } finally {
                csrfRefreshPromise = null;
            }
        })();

        return csrfRefreshPromise;
    };

    const requestErrorMessage = (response, payload, fallbackMessage) => {
        const backendMessage = payload?.message ?? firstValidationError(payload);

        if (typeof backendMessage === 'string' && backendMessage.trim() !== '') {
            return backendMessage;
        }

        return fallbackMessage;
    };

    const assistantFetch = async (url, {
        method = 'GET',
        body = null,
        signal = null,
        headers = {},
        accept = 'application/json',
        contentType = null,
        responseType = 'json',
        fallbackMessage = 'The assistant could not process that request.',
    } = {}) => {
        const response = await fetch(url, {
            method,
            body,
            signal,
            credentials: 'same-origin',
            headers: {
                ...buildRequestHeaders({ contentType, accept }),
                ...headers,
            },
        });

        if (!response.ok) {
            const payload = await safeJson(response);

            if (response.status === 419) {
                const refreshedToken = await refreshCsrfToken();

                throw createAssistantError(
                    refreshedToken ? sessionExpiredRetryMessage : sessionExpiredReloadMessage,
                    {
                        code: 'assistant-session-expired',
                        status: 419,
                        canRetry: Boolean(refreshedToken),
                    },
                );
            }

            throw createAssistantError(
                requestErrorMessage(response, payload, fallbackMessage),
                {
                    status: response.status,
                    payload,
                },
            );
        }

        if (responseType === 'raw') {
            return response;
        }

        return (await safeJson(response)) ?? {};
    };

    const isSessionExpiredError = (error) => error instanceof Error && error.code === 'assistant-session-expired';

    const clearPendingAssistantRetry = () => {
        pendingAssistantRetry = null;
    };

    const setPendingAssistantRetry = (retryHandler) => {
        pendingAssistantRetry = retryHandler;
    };

    const replayPendingAssistantRetry = async () => {
        if (!pendingAssistantRetry || hasActiveComposerRequest()) {
            return;
        }

        if (!csrfToken()) {
            const refreshedToken = await refreshCsrfToken();

            if (!refreshedToken) {
                appendResponse('assistant', buildSessionExpiredPayload({ canRetry: false }));
                return;
            }
        }

        await pendingAssistantRetry();
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

    const hasActiveComposerRequest = () => activeComposerRequest !== null;

    const setControlDisabled = (element, disabled) => {
        if (!(element instanceof HTMLElement)) {
            return;
        }

        if ('disabled' in element) {
            element.disabled = disabled;
        }

        element.setAttribute('aria-disabled', disabled ? 'true' : 'false');
    };

    const syncComposerInteractivity = () => {
        const isBusy = hasActiveComposerRequest();
        const isVisualBusy = activeComposerRequest === 'visual';
        const hasVisualFile = hasSelectedVisualFile();
        const visualCanRetry = hasVisualFile && visualUiState === 'failed' && !isBusy;
        const sendDisabled = isBusy || (hasVisualFile && visualUiState === 'processing');

        root.dataset.chatBusy = activeComposerRequest ?? 'idle';
        root.classList.toggle('is-busy', isBusy);

        if (sendButton) {
            const busyLabel = isVisualBusy ? 'Searching image' : 'Sending message';
            sendButton.classList.toggle('is-loading', isBusy);
            sendButton.setAttribute('aria-busy', isBusy ? 'true' : 'false');
            sendButton.setAttribute('aria-label', isBusy ? busyLabel : 'Send message');
            sendButton.title = isBusy ? busyLabel : 'Send message';
            setControlDisabled(sendButton, sendDisabled);
        }

        if (sendButtonLabel) {
            sendButtonLabel.textContent = isBusy ? (isVisualBusy ? 'Searching image' : 'Sending message') : 'Send message';
        }

        sendButtonIcon?.classList.toggle('hidden', false);
        sendButtonSpinner?.classList.toggle('hidden', !isBusy);

        setControlDisabled(input, isBusy);
        setControlDisabled(visualTrigger, isBusy);
        setControlDisabled(toolToggle, isBusy);
        setControlDisabled(toolToggleInline, isBusy || !hasVisualFile);
        setControlDisabled(visualRerun, !hasVisualFile || isVisualBusy);
        setControlDisabled(visualRetry, !visualCanRetry);
        setControlDisabled(visualChipRetry, !visualCanRetry);
    };

    const setActiveComposerRequest = (mode) => {
        activeComposerRequest = mode;
        syncComposerInteractivity();
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

    const appendVisualAttachmentBubble = (file) => {
        if (!messages || !file) {
            return;
        }

        const wrapper = createMessageGroup('user');
        const bubble = document.createElement('div');
        bubble.className = 'ys-chat-image-bubble is-user';

        if (isBrowserPreviewableImage(file)) {
            const image = document.createElement('img');
            const bubblePreviewUrl = URL.createObjectURL(file);
            image.className = 'ys-chat-image-preview';
            image.src = bubblePreviewUrl;
            image.alt = normalizeText(file.name || 'Selected image');
            image.addEventListener('load', () => URL.revokeObjectURL(bubblePreviewUrl), { once: true });
            image.addEventListener('error', () => URL.revokeObjectURL(bubblePreviewUrl), { once: true });
            bubble.appendChild(image);
        }

        const copy = document.createElement('div');
        copy.className = 'ys-chat-image-copy';

        const title = document.createElement('p');
        title.className = 'ys-chat-image-title';
        title.textContent = 'Image search';

        const caption = document.createElement('p');
        caption.className = 'ys-chat-image-caption';
        caption.textContent = normalizeText(file.name || 'Attached image');

        copy.append(title, caption);
        bubble.appendChild(copy);
        wrapper.appendChild(bubble);
        messages.appendChild(wrapper);
        scrollMessagesToEnd();
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

        const wrapper = createMessageGroup(role, payload.variant ?? 'default');
        messages.appendChild(wrapper);
        renderResponsePayload(wrapper, role, payload);
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

    const payloadHasRenderableDetails = (role, payload) => role === 'assistant'
        && ((Array.isArray(payload.products) && payload.products.length > 0) || (Array.isArray(payload.actions) && payload.actions.length > 0));

    const normalizedPayloadAnswer = (payload) => normalizeText(payload?.answer ?? '');

    const payloadHasRenderableAnswer = (payload) => normalizedPayloadAnswer(payload).trim() !== '';

    const clearResponseDetails = (wrapper) => {
        wrapper?.querySelectorAll('.ys-chat-product-grid, .ys-chat-actions').forEach((node) => node.remove());
    };

    const renderResponsePayload = (wrapper, role, payload) => {
        if (!wrapper) {
            return;
        }

        const variant = payload.variant ?? 'default';
        wrapper.className = `ys-chat-message-group ${role === 'assistant' ? 'is-assistant' : 'is-user'}`;

        if (variant === 'system') {
            wrapper.classList.add('is-system');
        }

        const hasAnswer = payloadHasRenderableAnswer(payload);
        let bubble = wrapper.querySelector('.ys-chat-bubble');

        if (hasAnswer) {
            if (!bubble) {
                bubble = document.createElement('div');
                wrapper.appendChild(bubble);
            }

            bubble.className = `ys-chat-bubble ${role === 'assistant' ? 'is-assistant' : 'is-user'}`;

            if (variant === 'system') {
                bubble.classList.add('is-system');
            }

            bubble.textContent = normalizedPayloadAnswer(payload);
        } else if (bubble) {
            bubble.remove();
        }

        clearResponseDetails(wrapper);
        appendResponseDetails(wrapper, role, payload);

        if (!hasAnswer && !payloadHasRenderableDetails(role, payload)) {
            if (visualThreadState?.wrapper === wrapper) {
                visualThreadState = null;
            }

            wrapper.remove();
            return;
        }

        scrollMessagesToEnd();
    };

    const createStreamingAssistantResponse = () => {
        if (!messages) {
            return null;
        }

        const wrapper = createMessageGroup('assistant');
        const bubble = document.createElement('div');
        bubble.className = 'ys-chat-bubble is-assistant';
        wrapper.appendChild(bubble);
        wrapper.hidden = true;
        messages.appendChild(wrapper);

        return { wrapper, bubble };
    };

    const finalizeStreamingAssistantResponse = (wrapper, bubble, payload) => {
        if (!wrapper || !bubble) {
            return;
        }

        wrapper.hidden = false;
        renderResponsePayload(wrapper, 'assistant', payload);
    };

    const activeVisualThread = () => {
        if (!visualThreadState?.wrapper || !visualThreadState.wrapper.isConnected) {
            return null;
        }

        return visualThreadState.selectionId === visualSelectionId ? visualThreadState : null;
    };

    const ensureVisualThread = () => {
        const current = activeVisualThread();

        if (current) {
            return current;
        }

        if (!messages) {
            return null;
        }

        const wrapper = createMessageGroup('assistant', 'system');
        messages.appendChild(wrapper);
        visualThreadState = {
            selectionId: visualSelectionId,
            wrapper,
        };

        return visualThreadState;
    };

    const renderVisualThreadPayload = (payload) => {
        const thread = ensureVisualThread();

        if (!thread) {
            return;
        }

        renderResponsePayload(thread.wrapper, 'assistant', payload);
    };

    const visualResponseCopy = (payload) => {
        const normalizedAnswer = normalizedPayloadAnswer(payload);
        const fallbackReason = String(payload?.match?.reason ?? payload?.visual_search?.reason ?? '').trim();
        const matchConfidence = String(payload?.match?.confidence ?? '').trim();

        if (payload?.status === 'failed' || payload?.search_confidence === 'failed') {
            return normalizedAnswer;
        }

        if (fallbackReason === 'filter_fallback') {
            return normalizedAnswer || 'No exact match found. Showing closest alternatives.';
        }

        if (matchConfidence === 'strong_match' || payload?.search_confidence === 'high_confidence') {
            return 'Found a strong match for this shoe.';
        }

        if (matchConfidence === 'likely_match' || payload?.search_confidence === 'medium_confidence') {
            return 'This looks like a close match.';
        }

        if (payload?.search_confidence === 'low_confidence' || matchConfidence === 'approximate_match') {
            return normalizedAnswer || 'This looks like a nearby match.';
        }

        return normalizedAnswer;
    };

    const normalizeVisualPayload = (payload) => ({
        ...(payload ?? {}),
        answer: visualResponseCopy(payload ?? {}),
    });

    const visualStateConfig = (state, fallbackMessage = '') => {
        switch (state) {
            case 'processing':
                return {
                    badge: 'Scanning',
                    message: fallbackMessage || 'Scanning your photo...',
                    chipClass: 'is-processing',
                    canRetry: false,
                };
            case 'success':
                return {
                    badge: 'Matched',
                    message: fallbackMessage || 'Closest visual matches ready.',
                    chipClass: 'is-success',
                    canRetry: false,
                };
            case 'medium-confidence':
                return {
                    badge: 'Similar',
                    message: fallbackMessage || 'Similar styles ready.',
                    chipClass: 'is-medium-confidence',
                    canRetry: false,
                };
            case 'low-confidence':
                return {
                    badge: 'Nearby',
                    message: fallbackMessage || 'Nearby catalog options ready.',
                    chipClass: 'is-low-confidence',
                    canRetry: false,
                };
            case 'failed':
                return {
                    badge: 'Retry',
                    message: fallbackMessage || 'I couldn\'t scan that image. Try another photo.',
                    chipClass: 'is-failed',
                    canRetry: true,
                };
            default:
                return {
                    badge: 'Ready',
                    message: fallbackMessage || 'Image attached. Press send to search.',
                    chipClass: 'is-idle',
                    canRetry: false,
                };
        }
    };

    const applyVisualUiState = (state, message = '') => {
        visualUiState = state;
        const config = visualStateConfig(state, message);

        if (visualStateChip) {
            visualStateChip.textContent = config.badge;
            visualStateChip.className = `ys-chat-visual-state-chip ${config.chipClass}`;
        }

        if (visualStatusCopy) {
            visualStatusCopy.textContent = config.message;
        }

        if (visualRetry) {
            visualRetry.classList.toggle('hidden', !config.canRetry);
            visualRetry.classList.toggle('inline-flex', config.canRetry);
        }

        if (visualChipBadge) {
            visualChipBadge.textContent = config.badge;
        }

        if (visualChipRetry) {
            visualChipRetry.classList.toggle('hidden', !config.canRetry);
            visualChipRetry.classList.toggle('inline-flex', config.canRetry);
        }

        if (visualRerun) {
            visualRerun.disabled = !selectedVisualFile || state === 'processing';
        }

        syncComposerInteractivity();
    };

    const applyVisualPayloadState = (payload) => {
        const visualPayload = normalizeVisualPayload(payload);

        if (visualPayload?.status === 'success' && visualPayload?.search_confidence === 'high_confidence') {
            applyVisualUiState('success', visualPayload.answer);
            return;
        }

        if (visualPayload?.status === 'success' && visualPayload?.search_confidence === 'medium_confidence') {
            applyVisualUiState('medium-confidence', visualPayload.answer);
            return;
        }

        if (visualPayload?.status === 'success' && visualPayload?.search_confidence === 'low_confidence') {
            applyVisualUiState('low-confidence', visualPayload.answer);
            return;
        }

        if (visualPayload?.status === 'failed' || visualPayload?.search_confidence === 'failed') {
            applyVisualUiState('failed', visualPayload.answer);
            return;
        }

        applyVisualUiState('idle');
    };

    const cancelActiveVisualRequest = () => {
        if (activeVisualRequest) {
            activeVisualRequest.abort();
            activeVisualRequest = null;
        }
    };

    const clearPendingFileInput = () => {
        if (visualInput) {
            visualInput.value = '';
        }
    };

    const hasSelectedVisualFile = () => Boolean(selectedVisualFile);

    const selectedVisualFileName = () => selectedVisualFile?.name ?? '';

    const selectedVisualInputFile = () => visualInput?.files?.[0] ?? null;

    const isBrowserPreviewableImage = (file) => {
        const extension = String(file?.name ?? '')
            .split('.')
            .pop()
            ?.toLowerCase();

        if (['heic', 'heif'].includes(extension)) {
            return false;
        }

        return !['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence'].includes(file?.type ?? '');
    };

    const validateImageIntegrity = async (file) => {
        if (!file || !isBrowserPreviewableImage(file)) {
            return null;
        }

        const objectUrl = URL.createObjectURL(file);

        try {
            if (typeof window.createImageBitmap === 'function') {
                const bitmap = await window.createImageBitmap(file);
                bitmap.close();

                return null;
            }

            await new Promise((resolve, reject) => {
                const probe = new Image();
                probe.onload = () => resolve();
                probe.onerror = () => reject(new Error('The selected image looks invalid or corrupted.'));
                probe.src = objectUrl;
            });

            return null;
        } catch {
            return 'The selected image looks invalid or corrupted.';
        } finally {
            URL.revokeObjectURL(objectUrl);
        }
    };

    const resetPreview = () => {
        cancelActiveVisualRequest();
        selectedVisualFile = null;
        lastVisualSentSelectionId = 0;
        clearPendingFileInput();

        if (currentPreviewUrl) {
            URL.revokeObjectURL(currentPreviewUrl);
            currentPreviewUrl = null;
        }

        if (visualPreviewImage) {
            visualPreviewImage.src = '';
        }

        if (visualFileName) {
            visualFileName.textContent = '';
        }

        visualStatus?.classList.add('hidden');
        visualStatus?.classList.remove('grid');
        applyVisualUiState('idle');
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
        applyVisualUiState('idle', 'Image attached. Press send to search.');
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

    const resetRefineFields = () => {
        refineFields.forEach((field) => {
            if (field instanceof HTMLSelectElement) {
                field.selectedIndex = 0;

                return;
            }

            field.value = '';
        });
    };

    const resetVisualComposerState = ({ clearFilters = false, closeTools = false } = {}) => {
        if (activeComposerRequest === 'visual' && activeVisualThread()) {
            renderVisualThreadPayload({
                answer: 'Image search canceled.',
                variant: 'system',
            });
        }

        resetPreview();

        if (clearFilters) {
            resetRefineFields();
        }

        if (closeTools) {
            setToolDrawerOpen(false);
        }

        syncRefineSummary();
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
        const hasUpload = hasSelectedVisualFile();
        const filters = activeRefineFilters();
        const filterCount = filters.length;

        root.classList.toggle('has-visual-upload', hasUpload);

        if (refineCount) {
            refineCount.textContent = String(filterCount);
        }

        if (refineMeta) {
            refineMeta.textContent = hasUpload
                ? (filterCount ? `${filterCount} filter${filterCount === 1 ? '' : 's'} applied` : 'Optional filters')
                : 'Optional filters';
        }

        if (visualChip && visualChipText && visualChipBadge) {
            const chipSegments = [];

            if (selectedVisualFileName()) {
                chipSegments.push(selectedVisualFileName());
            }

            if (filterCount > 0) {
                chipSegments.push(`${filterCount} filter${filterCount === 1 ? '' : 's'}`);
            }

            visualChipText.textContent = chipSegments.join(' · ');
            visualChip.classList.toggle('hidden', !hasUpload);
            visualChip.classList.toggle('flex', hasUpload);
        }

        if (refineSummary) {
            const chips = [];

            if (hasUpload) {
                chips.push('<span class="ys-chat-refine-tag is-highlight">Photo ready</span>');
            }

            filters.forEach((filter) => {
                chips.push(`<span class="ys-chat-refine-tag">${escapeHtml(filter.label)}: ${escapeHtml(filter.value)}</span>`);
            });

            refineSummary.innerHTML = chips.join('');
            refineSummary.classList.toggle('hidden', chips.length === 0);
            refineSummary.classList.toggle('flex', chips.length > 0);
        }

        if (visualRerun) {
            visualRerun.disabled = !hasUpload || visualUiState === 'processing';
        }

        syncComposerInteractivity();
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
            return 'Please upload a JPG, PNG, or WEBP image.';
        }

        if (file.size > MAX_IMAGE_BYTES) {
            return 'Please use an image smaller than 10 MB.';
        }

        return null;
    };

    const postMessageJson = async (message) => {
        return await assistantFetch(messageEndpoint, {
            method: 'POST',
            contentType: 'application/json',
            accept: 'application/json',
            body: JSON.stringify({ message }),
            fallbackMessage: 'The assistant could not process that request.',
        });
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
            const response = await assistantFetch(messageStreamEndpoint, {
                method: 'POST',
                contentType: 'application/json',
                accept: 'text/event-stream',
                body: JSON.stringify({ message }),
                responseType: 'raw',
                fallbackMessage: 'The assistant could not process that request.',
            });

            if (!response.body) {
                throw createAssistantError('The assistant could not process that request.');
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

                    if (accumulated.trim() !== '') {
                        wrapper.hidden = false;
                    }

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

    const sendMessage = async (message, { skipUserEcho = false } = {}) => {
        const trimmed = message.trim();

        if (!trimmed || !messageEndpoint) {
            appendResponse('assistant', {
                answer: 'Please type a question or tap one of the quick prompts so I can help.',
            });
            return;
        }

        if (hasActiveComposerRequest()) {
            return;
        }

        if (input) {
            input.value = trimmed;
        }

        if (!skipUserEcho) {
            appendResponse('user', { answer: trimmed });
        }

        setActiveComposerRequest('message');
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

            clearPendingAssistantRetry();

            if (input) {
                input.value = '';
            }
        } catch (error) {
            if (isSessionExpiredError(error)) {
                setPendingAssistantRetry(async () => {
                    await sendMessage(trimmed, { skipUserEcho: true });
                });
                appendResponse('assistant', buildSessionExpiredPayload({ canRetry: error.canRetry }));
                return;
            }

            clearPendingAssistantRetry();
            appendResponse('assistant', {
                answer: error instanceof Error ? error.message : 'The assistant is temporarily unavailable. Please try again.',
                variant: 'system',
            });
        } finally {
            toggleTyping(false);
            setActiveComposerRequest(null);
        }
    };

    const submitVisualSearch = async ({ isRetry = false } = {}) => {
        const file = selectedVisualFile;

        if (!visualForm || !file || !visualSearchEndpoint) {
            applyVisualUiState('failed', 'Attach an image first.');
            return;
        }

        if (hasActiveComposerRequest()) {
            return;
        }

        const clientError = validateImage(file);

        if (clientError) {
            applyVisualUiState('failed', clientError);
            return;
        }

        const requestId = ++visualRequestId;
        const controller = new AbortController();
        activeVisualRequest = controller;
        const formData = new FormData(visualForm);
        formData.delete('image');
        formData.append('image', file, file.name);
        setActiveComposerRequest('visual');
        applyVisualUiState('processing');
        toggleTyping(false);

        if (!isRetry && lastVisualSentSelectionId !== visualSelectionId) {
            appendVisualAttachmentBubble(file);
            lastVisualSentSelectionId = visualSelectionId;
        }

        renderVisualThreadPayload({
            answer: isRetry ? 'Retrying that image search...' : 'Searching your image...',
            variant: 'system',
        });

        try {
            const startedAt = Date.now();
            const payload = normalizeVisualPayload(await assistantFetch(visualSearchEndpoint, {
                method: 'POST',
                body: formData,
                signal: controller.signal,
                fallbackMessage: 'Visual Search could not process that image.',
            }));

            if (requestId !== visualRequestId) {
                return;
            }

            const elapsed = Date.now() - startedAt;
            if (elapsed < 500) {
                await wait(500 - elapsed);
            }

            applyVisualPayloadState(payload);
            clearPendingAssistantRetry();

            if (payload?.status === 'success') {
                renderVisualThreadPayload(payload ?? {});
            } else if (payload?.status === 'failed') {
                renderVisualThreadPayload({
                    answer: payload.answer,
                    variant: 'system',
                    actions: [
                        { label: 'Retry image search', type: 'visual-retry' },
                        { label: 'Refine filters', type: 'panel', target: 'visual-search' },
                    ],
                });
            }
        } catch (error) {
            if (error instanceof DOMException && error.name === 'AbortError') {
                return;
            }

            if (requestId !== visualRequestId) {
                return;
            }

            if (isSessionExpiredError(error)) {
                setPendingAssistantRetry(async () => {
                    await submitVisualSearch({ isRetry: true });
                });
                applyVisualUiState('failed', error.message);
                renderVisualThreadPayload(buildSessionExpiredPayload({ canRetry: error.canRetry }));
                return;
            }

            clearPendingAssistantRetry();
            const answer = error instanceof Error ? error.message : 'Visual Search is temporarily unavailable. Please try again.';
            applyVisualUiState('failed', answer);
            renderVisualThreadPayload({
                answer,
                variant: 'system',
                actions: [
                    { label: 'Retry image search', type: 'visual-retry' },
                    { label: 'Refine filters', type: 'panel', target: 'visual-search' },
                ],
            });
        } finally {
            if (requestId === visualRequestId) {
                activeVisualRequest = null;
                toggleTyping(false);
                setActiveComposerRequest(null);
                syncRefineSummary();
            }
        }
    };

    const handleAttachImage = () => {
        if (activeComposerRequest === 'visual') {
            return;
        }

        setOpen(true);
        visualInput?.click();
    };

    const handleOpenRefinePanel = () => {
        if (activeComposerRequest === 'visual') {
            return;
        }

        setOpen(true);
        setToolDrawerOpen(true);
    };

    const handleImageSelected = async () => {
        const file = selectedVisualInputFile();
        const selectionId = ++visualSelectionId;
        const hadSelectedFile = hasSelectedVisualFile();

        if (!file) {
            clearPendingFileInput();
            return;
        }

        const clientError = validateImage(file);

        if (clientError) {
            clearPendingFileInput();
            if (!hadSelectedFile) {
                applyVisualUiState('failed', clientError);
            }
            appendResponse('assistant', { answer: clientError, variant: 'system' });
            return;
        }

        const integrityError = await validateImageIntegrity(file);

        if (selectionId !== visualSelectionId) {
            return;
        }

        if (integrityError) {
            clearPendingFileInput();
            if (!hadSelectedFile) {
                applyVisualUiState('failed', integrityError);
            }
            appendResponse('assistant', { answer: integrityError, variant: 'system' });
            return;
        }

        if (hadSelectedFile) {
            resetRefineFields();
        }

        selectedVisualFile = file;
        clearPendingFileInput();
        setOpen(true);
        setPreview(file);
        syncRefineSummary();
    };

    const handleSend = async () => {
        if (hasActiveComposerRequest()) {
            return;
        }

        if (hasSelectedVisualFile()) {
            await submitVisualSearch();
            return;
        }

        await sendMessage(input?.value ?? '');
    };

    const handleRetryImage = async () => {
        if (!hasSelectedVisualFile()) {
            applyVisualUiState('failed', 'Attach an image first.');
            return;
        }

        if (visualUiState !== 'failed' || hasActiveComposerRequest()) {
            return;
        }

        await submitVisualSearch({ isRetry: true });
    };

    const handleAction = (action) => {
        if (!action) {
            return;
        }

        if (action.type === 'message' && action.message) {
            sendMessage(action.message);
            return;
        }

        if (action.type === 'visual-retry') {
            handleRetryImage();
            return;
        }

        if (action.type === 'visual-upload') {
            handleAttachImage();
            return;
        }

        if (action.type === 'assistant-retry') {
            replayPendingAssistantRetry();
            return;
        }

        if (action.type === 'assistant-reload') {
            window.location.reload();
            return;
        }

        if (action.type === 'panel' && action.target === 'visual-search') {
            handleOpenRefinePanel();
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
        resetVisualComposerState({ clearFilters: true, closeTools: true });
    });

    minimizeButton?.addEventListener('click', () => {
        setOpen(false);
        resetVisualComposerState({ clearFilters: true, closeTools: true });
    });

    promptButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const prompt = button.dataset.chatPrompt ?? '';
            setOpen(true);

            if (prompt.toLowerCase().includes('image')) {
                handleOpenRefinePanel();
                return;
            }

            sendMessage(prompt);
        });
    });

    visualLaunchers.forEach((button) => {
        button.addEventListener('click', () => {
            setOpen(true);
            handleOpenRefinePanel();
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
        handleSend();
    });

    input?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        handleSend();
    });

    visualTrigger?.addEventListener('click', () => {
        handleAttachImage();
    });

    toolToggle?.addEventListener('click', () => {
        setOpen(true);
        toggleToolDrawer();
    });

    toolToggleInline?.addEventListener('click', () => {
        handleOpenRefinePanel();
    });

    toolClose?.addEventListener('click', () => {
        setToolDrawerOpen(false);
    });

    visualClear?.addEventListener('click', () => {
        resetVisualComposerState({ clearFilters: true });
    });

    visualRerun?.addEventListener('click', () => {
        if (!hasSelectedVisualFile()) {
            applyVisualUiState('failed', 'Attach an image first.');
            return;
        }

        submitVisualSearch();
    });

    visualRetry?.addEventListener('click', () => {
        handleRetryImage();
    });

    visualChipRetry?.addEventListener('click', () => {
        handleRetryImage();
    });

    visualInput?.addEventListener('change', () => {
        handleImageSelected();
    });

    refineFields.forEach((field) => {
        field.addEventListener(field instanceof HTMLSelectElement ? 'change' : 'input', syncRefineSummary);
    });

    setToolDrawerOpen(false);
    applyVisualUiState('idle');
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
