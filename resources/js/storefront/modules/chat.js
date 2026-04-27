const MAX_IMAGE_BYTES = 4 * 1024 * 1024;
const ACCEPTED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

export const initChatWidget = () => {
    const root = document.querySelector('[data-chat-shell]');

    if (!root) {
        return;
    }

    const panel = root.querySelector('[data-chat-panel]');
    const toggle = root.querySelector('[data-chat-toggle]');
    const closeButton = root.querySelector('[data-chat-close]');
    const minimizeButton = root.querySelector('[data-chat-minimize]');
    const tabs = Array.from(root.querySelectorAll('[data-chat-tab]'));
    const views = Array.from(root.querySelectorAll('[data-chat-view]'));
    const form = root.querySelector('[data-chat-form]');
    const input = root.querySelector('[data-chat-input]');
    const messages = root.querySelector('[data-chat-messages]');
    const typing = root.querySelector('[data-chat-typing]');
    const promptButtons = Array.from(root.querySelectorAll('[data-chat-prompt]'));
    const visualLaunchers = Array.from(document.querySelectorAll('[data-chat-open-visual]'));
    const visualForm = root.querySelector('[data-visual-search-form]');
    const visualDropzone = root.querySelector('[data-visual-dropzone]');
    const visualInput = root.querySelector('[data-visual-file-input]');
    const visualTrigger = root.querySelector('[data-visual-file-trigger]');
    const visualClear = root.querySelector('[data-visual-clear]');
    const visualPreviewShell = root.querySelector('[data-visual-preview-shell]');
    const visualPreviewImage = root.querySelector('[data-visual-preview-image]');
    const visualFileName = root.querySelector('[data-visual-file-name]');
    const visualEmptyState = root.querySelector('[data-visual-empty-state]');
    const visualResults = root.querySelector('[data-visual-results]');
    const visualLoading = root.querySelector('[data-visual-loading]');
    const messageEndpoint = root.dataset.messageEndpoint;
    const visualSearchEndpoint = root.dataset.visualSearchEndpoint;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    let currentTab = 'assistant';
    let currentPreviewUrl = null;

    const setOpen = (isOpen) => {
        if (!panel) {
            return;
        }

        panel.classList.toggle('is-open', isOpen);
        panel.dataset.open = isOpen ? 'true' : 'false';
        panel.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        panel.style.opacity = isOpen ? '1' : '0';
        panel.style.pointerEvents = isOpen ? 'auto' : 'none';
        panel.style.visibility = isOpen ? 'visible' : 'hidden';
        panel.style.transform = isOpen ? 'translateY(0) scale(1)' : 'translateY(16px) scale(0.98)';
        toggle?.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    };

    const setTab = (tabName) => {
        currentTab = tabName;

        tabs.forEach((tab) => {
            tab.classList.toggle('is-active', tab.dataset.chatTab === tabName);
        });

        views.forEach((view) => {
            const isActive = view.dataset.chatView === tabName;
            view.classList.toggle('hidden', !isActive);
            view.classList.toggle('flex', isActive);
        });
    };

    const escapeHtml = (value) =>
        value
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');

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
                        <div>
                            <p class="ys-assistant-product-title">${escapeHtml(product.name)}</p>
                            <p class="ys-assistant-product-meta">${escapeHtml(product.category)}</p>
                        </div>
                        <span class="ys-assistant-stock is-${escapeHtml(product.availability.state)}">${escapeHtml(product.availability.label)}</span>
                    </div>
                    <p class="mt-3 text-sm font-semibold text-ys-ivory">${escapeHtml(product.price_label)}</p>
                    <p class="mt-1 text-xs leading-5 text-ys-ivory/46">${escapeHtml(product.short_description ?? 'Explore this product in the storefront catalog.')}</p>
                    <div class="mt-3 flex flex-wrap gap-2 text-[11px] uppercase tracking-[0.18em] text-ys-ivory/38">
                        ${product.colors?.length ? `<span>${escapeHtml(product.colors.slice(0, 2).join(' / '))}</span>` : ''}
                        ${product.sizes?.length ? `<span>Sizes ${escapeHtml(product.sizes.join(', '))}</span>` : ''}
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

    const appendMessage = (role, payload) => {
        if (!messages) {
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.className = `ys-chat-message-group ${role === 'assistant' ? 'is-assistant' : 'is-user'}`;

        const bubble = document.createElement('div');
        bubble.className = `ys-chat-bubble ${role === 'assistant' ? 'is-assistant' : 'is-user'}`;
        bubble.textContent = payload.answer;
        wrapper.appendChild(bubble);

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

        messages.appendChild(wrapper);
        messages.scrollTop = messages.scrollHeight;
    };

    const toggleTyping = (isVisible) => {
        typing?.classList.toggle('hidden', !isVisible);
        typing?.classList.toggle('flex', isVisible);
    };

    const autosizeInput = () => {
        if (!input) {
            return;
        }

        input.style.height = '0px';
        input.style.height = `${Math.min(input.scrollHeight, 128)}px`;
    };

    const sendMessage = async (message) => {
        const trimmed = message.trim();

        if (!trimmed || !messageEndpoint) {
            appendMessage('assistant', {
                answer: 'Please type a question or tap one of the quick prompts so I can help.',
            });
            return;
        }

        appendMessage('user', { answer: trimmed });
        if (input) {
            input.value = '';
            autosizeInput();
        }

        toggleTyping(true);

        try {
            const startedAt = Date.now();
            const response = await fetch(messageEndpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ message: trimmed }),
            });

            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.message ?? firstValidationError(payload) ?? 'The assistant could not process that request.');
            }

            const elapsed = Date.now() - startedAt;
            if (elapsed < 500) {
                await wait(500 - elapsed);
            }

            appendMessage('assistant', payload);
        } catch (error) {
            appendMessage('assistant', {
                answer: error instanceof Error ? error.message : 'The assistant is temporarily unavailable. Please try again.',
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
            setTab('visual-search');
        }
    };

    const renderVisualState = (content) => {
        if (!visualResults) {
            return;
        }

        visualResults.innerHTML = content;
        visualResults.scrollTop = 0;
    };

    const renderVisualResponse = (payload) => {
        const products = payload.products?.length
            ? `<div class="ys-chat-product-grid">${payload.products.map(renderProductCard).join('')}</div>`
            : '<div class="ys-visual-results-empty">No similar products matched the current image hints.</div>';

        renderVisualState(`
            <div class="ys-visual-response">
                <p class="ys-chat-bubble is-assistant">${escapeHtml(payload.answer)}</p>
                ${products}
                ${renderActions(payload.actions)}
            </div>
        `);
    };

    const resetPreview = () => {
        if (currentPreviewUrl) {
            URL.revokeObjectURL(currentPreviewUrl);
            currentPreviewUrl = null;
        }

        if (visualInput) {
            visualInput.value = '';
        }

        visualPreviewImage?.setAttribute('src', '');
        visualFileName.textContent = '';
        visualPreviewShell?.classList.add('hidden');
        visualPreviewShell?.classList.remove('grid');
        visualEmptyState?.classList.remove('hidden');
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
        visualPreviewShell?.classList.remove('hidden');
        visualPreviewShell?.classList.add('grid');
        visualEmptyState?.classList.add('hidden');
    };

    const validateImage = (file) => {
        if (!file) {
            return 'Select an image first to use Visual Search.';
        }

        if (!ACCEPTED_IMAGE_TYPES.includes(file.type)) {
            return 'Please upload a JPG, PNG, or WEBP image.';
        }

        if (file.size > MAX_IMAGE_BYTES) {
            return 'Please use an image smaller than 4 MB.';
        }

        return null;
    };

    const submitVisualSearch = async () => {
        if (!visualForm || !visualInput?.files?.[0] || !visualSearchEndpoint) {
            renderVisualState('<div class="ys-visual-results-empty">Select an image first to use Visual Search.</div>');
            return;
        }

        const file = visualInput.files[0];
        const clientError = validateImage(file);

        if (clientError) {
            renderVisualState(`<div class="ys-visual-results-empty">${escapeHtml(clientError)}</div>`);
            return;
        }

        const formData = new FormData(visualForm);
        visualLoading?.classList.remove('hidden');

        try {
            const response = await fetch(visualSearchEndpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: formData,
            });

            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.message ?? firstValidationError(payload) ?? 'Visual Search could not process that image.');
            }

            renderVisualResponse(payload);
        } catch (error) {
            renderVisualState(
                `<div class="ys-visual-results-empty">${escapeHtml(
                    error instanceof Error ? error.message : 'Visual Search is temporarily unavailable. Please try again.',
                )}</div>`,
            );
        } finally {
            visualLoading?.classList.add('hidden');
        }
    };

    toggle?.addEventListener('click', () => {
        const isOpen = panel?.classList.contains('is-open');
        setOpen(!isOpen);
        if (!isOpen && currentTab === 'assistant') {
            input?.focus();
        }
    });

    closeButton?.addEventListener('click', () => {
        setOpen(false);
        setTab('assistant');
    });

    minimizeButton?.addEventListener('click', () => {
        setOpen(false);
    });

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            setOpen(true);
            setTab(tab.dataset.chatTab ?? 'assistant');
        });
    });

    promptButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const prompt = button.dataset.chatPrompt ?? '';
            setOpen(true);

            if (prompt.toLowerCase().includes('image')) {
                setTab('visual-search');
                return;
            }

            setTab('assistant');
            sendMessage(prompt);
        });
    });

    visualLaunchers.forEach((button) => {
        button.addEventListener('click', () => {
            setOpen(true);
            setTab('visual-search');
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

    input?.addEventListener('input', autosizeInput);
    input?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage(input.value);
        }
    });

    visualTrigger?.addEventListener('click', () => visualInput?.click());
    visualClear?.addEventListener('click', () => {
        resetPreview();
        renderVisualState('<div class="ys-visual-results-empty">Upload an image to see similar products, availability, and direct product links here.</div>');
    });

    visualInput?.addEventListener('change', () => {
        const file = visualInput.files?.[0];
        const clientError = validateImage(file);

        if (clientError) {
            resetPreview();
            renderVisualState(`<div class="ys-visual-results-empty">${escapeHtml(clientError)}</div>`);
            return;
        }

        if (file) {
            setPreview(file);
        }
    });

    visualDropzone?.addEventListener('dragover', (event) => {
        event.preventDefault();
        visualDropzone.classList.add('is-dragging');
    });

    visualDropzone?.addEventListener('dragleave', () => {
        visualDropzone.classList.remove('is-dragging');
    });

    visualDropzone?.addEventListener('drop', (event) => {
        event.preventDefault();
        visualDropzone.classList.remove('is-dragging');

        const file = event.dataTransfer?.files?.[0];

        if (!file || !visualInput?.files) {
            return;
        }

        const transfer = new DataTransfer();
        transfer.items.add(file);
        visualInput.files = transfer.files;
        visualInput.dispatchEvent(new Event('change', { bubbles: true }));
    });

    visualForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        submitVisualSearch();
    });

    autosizeInput();
    setTab('assistant');
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
