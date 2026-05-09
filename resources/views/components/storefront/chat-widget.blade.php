@php
    $promptChips = config('storefront.assistant.prompt_chips', []);
    $visualColors = config('storefront.assistant.visual_search.colors', []);
    $visualUseCases = config('storefront.assistant.visual_search.use_cases', []);
@endphp

<div
    class="ys-chat-shell fixed inset-x-4 bottom-4 z-[60] sm:inset-x-auto sm:bottom-5 sm:right-5"
    data-chat-shell
    data-chat-state="closed"
    data-message-endpoint="{{ route('storefront.assistant.message') }}"
    data-message-stream-endpoint="{{ route('storefront.assistant.message.stream') }}"
    data-visual-search-endpoint="{{ route('storefront.assistant.visual-search') }}"
>
    <div
        class="ys-chat-panel pointer-events-none absolute bottom-[calc(100%+1rem)] right-0 flex w-full max-w-[30rem] flex-col opacity-0 sm:w-[30rem]"
        data-chat-panel
        aria-hidden="true"
    >
        <div class="ys-chat-header">
            <div class="ys-chat-header-copy">
                <p class="ys-chat-title">Smart Shopping Assistant</p>
                <p class="ys-chat-subtitle">Live catalog help, visual product discovery, and guided shopping support.</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" class="ys-chat-control" data-chat-minimize aria-label="Minimize assistant" title="Minimize assistant">
                    <span class="block h-0.5 w-3 rounded-full bg-current"></span>
                </button>
                <button type="button" class="ys-chat-control" data-chat-close aria-label="Close assistant" title="Close assistant">
                    <span class="text-base leading-none">&times;</span>
                </button>
            </div>
        </div>

        <section class="ys-chat-thread-shell">
            <div class="ys-chat-messages" data-chat-messages>
                <div class="ys-chat-message-group is-assistant">
                    <div class="ys-chat-bubble is-assistant">
                        Ask me for products, budgets, size guidance, stock checks, cart help, checkout help, or upload an image to find similar shoes.
                    </div>
                    <div class="ys-chat-chip-list is-inline">
                        @foreach ($promptChips as $chip)
                            @php
                                $chipLabel = str_replace(['Ã¢â€šÂ±', 'â‚±'], '₱', $chip);
                            @endphp
                            <button type="button" class="ys-chat-chip" data-chat-prompt="{{ $chipLabel }}">
                                {{ $chipLabel }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="hidden items-center gap-2 px-5 pb-4 text-xs text-ys-ivory/58" data-chat-typing>
                <span class="ys-chat-typing-dots">
                    <span></span>
                    <span></span>
                    <span></span>
                </span>
                <span data-chat-typing-label>Assistant is checking the catalog...</span>
            </div>
        </section>

        <div class="ys-chat-footer">
            <form class="ys-chat-visual-form" data-visual-search-form enctype="multipart/form-data" novalidate>
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" class="sr-only" data-visual-file-input>

                <div
                    id="assistant-image-tools"
                    class="ys-chat-tool-drawer hidden"
                    data-chat-tool-drawer
                    aria-hidden="true"
                >
                    <div class="ys-chat-tool-drawer-header">
                        <div class="ys-chat-tool-drawer-copy">
                            <p class="ys-chat-tool-drawer-title">Image Search</p>
                            <p class="ys-chat-tool-drawer-meta">Upload a shoe photo and refine only if you need to.</p>
                        </div>

                        <button
                            type="button"
                            class="ys-chat-tool-close"
                            data-chat-tools-close
                            aria-label="Close image search tools"
                            title="Close image search tools"
                        >
                            <span class="text-base leading-none">&times;</span>
                        </button>
                    </div>

                    <div class="ys-chat-visual-status hidden max-w-full overflow-hidden" data-visual-status>
                        <img src="" alt="Selected visual search preview" class="ys-chat-visual-status-image" data-visual-preview-image>
                        <div class="ys-chat-visual-status-body min-w-0">
                            <div class="ys-chat-visual-status-top min-w-0">
                                <p class="ys-chat-visual-status-name" data-visual-file-name></p>
                                <span class="ys-chat-visual-state-chip is-idle" data-visual-state-chip>Ready</span>
                            </div>
                            <p class="ys-chat-visual-status-copy" data-visual-status-copy>Ready to scan.</p>
                            <div class="ys-chat-refine-tags hidden" data-visual-filter-summary aria-live="polite"></div>
                        </div>
                        <div class="ys-chat-visual-status-actions">
                            <button type="button" class="ys-chat-icon-button hidden" data-visual-retry aria-label="Retry image search" title="Retry image search">
                                <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M20 12a8 8 0 1 1-2.34-5.66" stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M20 4v6h-6" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                            <button type="button" class="ys-chat-icon-button" data-visual-clear aria-label="Remove image" title="Remove image">
                                <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M5 7h14" stroke-linecap="round" />
                                    <path d="M10 11v5" stroke-linecap="round" />
                                    <path d="M14 11v5" stroke-linecap="round" />
                                    <path d="M7 7l1 11a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2l1-11" stroke-linejoin="round" />
                                    <path d="M9 7V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5V7" stroke-linejoin="round" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="ys-chat-refine-summary max-w-full overflow-hidden">
                        <span class="ys-chat-refine-summary-copy min-w-0">
                            <span class="ys-chat-refine-summary-title">Refine</span>
                            <span class="ys-chat-refine-summary-meta" data-visual-refine-meta>Optional filters</span>
                        </span>
                        <span class="ys-chat-refine-summary-state">
                            <span class="ys-chat-refine-summary-count" data-visual-filter-count>0</span>
                            <button type="button" class="ys-button-secondary ys-chat-rerun-button text-[0.8rem]" data-visual-rerun>
                                Search
                            </button>
                        </span>
                    </div>

                    <div class="ys-chat-refine-panel">
                        <div class="ys-chat-refine-grid">
                            <label class="ys-field">
                                <span>Brand or style</span>
                                <input type="text" name="brand_style" class="ys-input h-11" placeholder="Example: Onyx, runner, court" data-visual-filter-field data-filter-label="Brand/style">
                            </label>

                            <label class="ys-field">
                                <span>Color</span>
                                <select name="color" class="ys-select h-11" data-visual-filter-field data-filter-label="Color">
                                    <option value="">Any color</option>
                                    @foreach ($visualColors as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="ys-field">
                                <span>Category</span>
                                <select name="category" class="ys-select h-11" data-visual-filter-field data-filter-label="Category">
                                    <option value="">Any category</option>
                                    @foreach ($storefrontCategories ?? [] as $category)
                                        <option value="{{ $category->slug }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="ys-field">
                                <span>Use case</span>
                                <select name="use_case" class="ys-select h-11" data-visual-filter-field data-filter-label="Use case">
                                    <option value="">Any use case</option>
                                    @foreach ($visualUseCases as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    </div>
                </div>
            </form>

            <form class="ys-chat-input-bar w-full min-w-0 max-w-full overflow-hidden" data-chat-form>
                <button type="button" class="ys-chat-input-icon" data-visual-file-trigger aria-label="Upload image" title="Upload image">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path d="M12 16V7.5" stroke-linecap="round" />
                        <path d="m8.5 11 3.5-3.5 3.5 3.5" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M5 16.5v.5A2.5 2.5 0 0 0 7.5 19h9a2.5 2.5 0 0 0 2.5-2.5v-.5" stroke-linecap="round" />
                        <rect x="4" y="4" width="16" height="16" rx="4" />
                    </svg>
                </button>

                <button
                    type="button"
                    class="ys-chat-input-icon"
                    data-chat-tools-toggle
                    aria-label="Open refine options"
                    title="Open refine options"
                    aria-expanded="false"
                    aria-controls="assistant-image-tools"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path d="M4 7h16" stroke-linecap="round" />
                        <path d="M7 12h10" stroke-linecap="round" />
                        <path d="M10 17h4" stroke-linecap="round" />
                        <circle cx="9" cy="7" r="1.5" fill="currentColor" stroke="none" />
                        <circle cx="15" cy="12" r="1.5" fill="currentColor" stroke="none" />
                        <circle cx="12" cy="17" r="1.5" fill="currentColor" stroke="none" />
                    </svg>
                </button>

                <div class="ys-chat-composer-body min-w-0 overflow-hidden">
                    <div class="ys-chat-composer-chip hidden max-w-full overflow-hidden" data-visual-chip>
                        <span class="ys-chat-composer-chip-badge" data-visual-chip-badge>Ready</span>
                        <span class="ys-chat-composer-chip-text" data-visual-chip-text></span>
                        <span class="ys-chat-composer-chip-actions">
                            <button type="button" class="ys-chat-composer-chip-action hidden" data-visual-chip-retry>
                                Retry
                            </button>
                            <button type="button" class="ys-chat-composer-chip-action" data-chat-tools-toggle-inline>
                                Refine
                            </button>
                        </span>
                    </div>

                    <label class="sr-only" for="assistant-message">Message assistant</label>
                    <input
                        id="assistant-message"
                        type="text"
                        name="message"
                        class="ys-chat-input-bar-field"
                        placeholder="Ask about shoes, size, stock, budget, or upload a photo..."
                        maxlength="400"
                        data-chat-input
                    >
                </div>

                <button
                    type="submit"
                    class="ys-chat-send-button"
                    data-chat-send
                    aria-label="Send message"
                    title="Send message"
                >
                    <span class="ys-chat-send-button-icon" data-chat-send-icon aria-hidden="true">
                        <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                            <path d="M4 12 20 4l-4.5 16-4-6-7.5-2Z" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <span class="ys-chat-send-button-spinner hidden" data-chat-send-spinner aria-hidden="true"></span>
                    <span class="sr-only" data-chat-send-label>Send message</span>
                </button>
            </form>
        </div>
    </div>

    <button type="button" class="ys-chat-trigger" data-chat-toggle aria-label="Open assistant" title="Open assistant">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path d="M7 17.5 4 20V6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v8A2.5 2.5 0 0 1 17.5 17H7Z" stroke-linejoin="round" />
            <path d="M8.5 9.5h7M8.5 13h4.5" stroke-linecap="round" />
        </svg>
    </button>
</div>
