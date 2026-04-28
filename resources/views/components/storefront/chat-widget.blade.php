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
                <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" class="sr-only" data-visual-file-input>

                <div class="ys-chat-visual-status hidden" data-visual-status>
                    <img src="" alt="Selected visual search preview" class="ys-chat-visual-status-image" data-visual-preview-image>
                    <div class="min-w-0 flex-1">
                        <p class="ys-chat-visual-status-name" data-visual-file-name></p>
                        <p class="ys-chat-visual-status-copy">Ready for visual search. Use refine controls below if you want a narrower match.</p>
                    </div>
                    <button type="button" class="ys-chat-inline-link" data-visual-clear>
                        Remove
                    </button>
                </div>

                <details class="ys-chat-refine-details">
                    <summary class="ys-chat-refine-summary">Refine image search</summary>

                    <div class="ys-chat-refine-panel">
                        <div class="ys-chat-refine-grid">
                            <label class="ys-field">
                                <span>Brand or style</span>
                                <input type="text" name="brand_style" class="ys-input h-11" placeholder="Example: Onyx, runner, court">
                            </label>

                            <label class="ys-field">
                                <span>Color</span>
                                <select name="color" class="ys-select h-11">
                                    <option value="">Any color</option>
                                    @foreach ($visualColors as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="ys-field">
                                <span>Category</span>
                                <select name="category" class="ys-select h-11">
                                    <option value="">Any category</option>
                                    @foreach ($storefrontCategories ?? [] as $category)
                                        <option value="{{ $category->slug }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="ys-field">
                                <span>Use case</span>
                                <select name="use_case" class="ys-select h-11">
                                    <option value="">Any use case</option>
                                    @foreach ($visualUseCases as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        <div class="ys-chat-refine-actions">
                            <button type="button" class="ys-button-secondary text-[0.84rem]" data-visual-rerun>
                                Search Uploaded Image
                            </button>
                        </div>
                    </div>
                </details>
            </form>

            <form class="ys-chat-input-bar" data-chat-form>
                <button type="button" class="ys-chat-input-icon" data-visual-file-trigger aria-label="Upload image" title="Upload image">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path d="M12 16V7.5" stroke-linecap="round" />
                        <path d="m8.5 11 3.5-3.5 3.5 3.5" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M5 16.5v.5A2.5 2.5 0 0 0 7.5 19h9a2.5 2.5 0 0 0 2.5-2.5v-.5" stroke-linecap="round" />
                        <rect x="4" y="4" width="16" height="16" rx="4" />
                    </svg>
                </button>

                <label class="sr-only" for="assistant-message">Message assistant</label>
                <input
                    id="assistant-message"
                    type="text"
                    name="message"
                    class="ys-chat-input-bar-field"
                    placeholder="Ask about shoes, size, stock, or budget..."
                    maxlength="400"
                    data-chat-input
                >

                <button type="submit" class="ys-chat-send-button">
                    Send
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
