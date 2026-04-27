@php
    $promptChips = config('storefront.assistant.prompt_chips', []);
    $visualColors = config('storefront.assistant.visual_search.colors', []);
    $visualUseCases = config('storefront.assistant.visual_search.use_cases', []);
@endphp

<div
    class="fixed inset-x-4 bottom-4 z-[60] sm:inset-x-auto sm:bottom-5 sm:right-5"
    data-chat-shell
    data-message-endpoint="{{ route('storefront.assistant.message') }}"
    data-visual-search-endpoint="{{ route('storefront.assistant.visual-search') }}"
>
    <div
        class="ys-chat-panel pointer-events-none absolute bottom-[calc(100%+1rem)] right-0 flex w-full max-w-[24rem] flex-col opacity-0 sm:w-[24rem]"
        data-chat-panel
        aria-hidden="true"
    >
        <div class="flex items-start justify-between gap-4 border-b border-white/6 px-5 py-4">
            <div>
                <p class="text-sm font-semibold text-ys-ivory">Smart Shopping Assistant</p>
                <p class="mt-1 text-xs text-ys-ivory/45">Live catalog, stock, cart, and checkout guidance</p>
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

        <div class="border-b border-white/6 px-3 py-3">
            <div class="grid grid-cols-2 gap-2 rounded-[1rem] bg-black/25 p-1.5">
                <button type="button" class="ys-chat-tab is-active" data-chat-tab="assistant">Assistant</button>
                <button type="button" class="ys-chat-tab" data-chat-tab="visual-search">Visual Search</button>
            </div>
        </div>

        <section class="flex min-h-0 flex-1 flex-col px-4 pb-4 pt-3" data-chat-view="assistant">
            <div class="mb-3 flex flex-wrap gap-2">
                @foreach ($promptChips as $chip)
                    <button type="button" class="ys-chat-chip" data-chat-prompt="{{ $chip }}">
                        {{ $chip }}
                    </button>
                @endforeach
            </div>

            <div class="ys-chat-messages flex-1 overflow-y-auto pr-1" data-chat-messages>
                <div class="ys-chat-message-group is-assistant">
                    <div class="ys-chat-bubble is-assistant">
                        Ask me for products, budgets, size guidance, stock checks, cart help, checkout help, or similar shoes by image.
                    </div>
                </div>
            </div>

            <div class="hidden items-center gap-2 px-1 py-2 text-xs text-ys-ivory/52" data-chat-typing>
                <span class="ys-chat-typing-dots">
                    <span></span>
                    <span></span>
                    <span></span>
                </span>
                <span>Assistant is checking the catalog...</span>
            </div>

            <form class="mt-3 flex items-end gap-3" data-chat-form>
                <label class="sr-only" for="assistant-message">Message assistant</label>
                <textarea
                    id="assistant-message"
                    name="message"
                    rows="1"
                    class="ys-chat-input"
                    placeholder="Ask about shoes, stock, size, cart, or checkout..."
                    maxlength="400"
                    data-chat-input
                ></textarea>
                <button type="submit" class="ys-button-primary h-14 shrink-0 px-5 text-[0.92rem]">
                    Send
                </button>
            </form>

            <p class="mt-2 px-1 text-[11px] leading-5 text-ys-ivory/34">
                Responses are limited to the current Ysabelle catalog, inventory, cart, and storefront policies.
            </p>
        </section>

        <section class="hidden min-h-0 flex-1 flex-col px-4 pb-4 pt-3" data-chat-view="visual-search">
            <form class="flex h-full flex-col gap-4" data-visual-search-form enctype="multipart/form-data" novalidate>
                <div class="ys-visual-dropzone" data-visual-dropzone>
                    <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" class="sr-only" data-visual-file-input>

                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-ys-ivory">Find Similar Shoes</p>
                            <p class="mt-1 text-xs leading-5 text-ys-ivory/46">Drag a shoe image here or choose a file. The image is only used for this search session.</p>
                        </div>
                        <button type="button" class="ys-chat-chip whitespace-nowrap" data-visual-file-trigger>Choose image</button>
                    </div>

                    <div class="ys-visual-preview-empty mt-4" data-visual-empty-state>
                        Drop a JPG, PNG, or WEBP shoe image to start.
                    </div>

                    <div class="hidden mt-4 gap-3" data-visual-preview-shell>
                        <img src="" alt="Selected visual search preview" class="h-24 w-24 rounded-[1rem] border border-white/8 object-cover" data-visual-preview-image>
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-ys-ivory" data-visual-file-name></p>
                            <p class="mt-1 text-xs text-ys-ivory/46">Use the hint fields below to improve similarity matching.</p>
                            <button type="button" class="mt-3 text-xs font-semibold uppercase tracking-[0.22em] text-[#ef9191] transition hover:text-[#ffc8c8]" data-visual-clear>
                                Remove image
                            </button>
                        </div>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="ys-field">
                        <span>Brand or style hint</span>
                        <input type="text" name="brand_style" class="ys-input h-12" placeholder="Example: Onyx, runner, court">
                    </label>

                    <label class="ys-field">
                        <span>Color</span>
                        <select name="color" class="ys-select h-12">
                            <option value="">Any color</option>
                            @foreach ($visualColors as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="ys-field">
                        <span>Category</span>
                        <select name="category" class="ys-select h-12">
                            <option value="">Any category</option>
                            @foreach ($storefrontCategories ?? [] as $category)
                                <option value="{{ $category->slug }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="ys-field">
                        <span>Use case</span>
                        <select name="use_case" class="ys-select h-12">
                            <option value="">Any use case</option>
                            @foreach ($visualUseCases as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" class="ys-button-primary flex-1 justify-center text-[0.92rem]">
                        Search similar products
                    </button>
                </div>

                <div class="ys-visual-feedback hidden" data-visual-loading>
                    Matching your image hints against the current catalog...
                </div>

                <div class="ys-visual-results flex-1 overflow-y-auto pr-1" data-visual-results>
                    <div class="ys-visual-results-empty">
                        Upload an image to see similar products, availability, and direct product links here.
                    </div>
                </div>
            </form>
        </section>
    </div>

    <button type="button" class="ys-chat-trigger" data-chat-toggle aria-label="Open assistant" title="Open assistant">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path d="M7 17.5 4 20V6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v8A2.5 2.5 0 0 1 17.5 17H7Z" stroke-linejoin="round" />
            <path d="M8.5 9.5h7M8.5 13h4.5" stroke-linecap="round" />
        </svg>
    </button>
</div>
