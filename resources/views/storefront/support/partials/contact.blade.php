@php
    $contact = $page['view_data'];
    $selectedIssue = old('category', 'order-issue');
    $selectedIssueConfig = $contact['issues'][$selectedIssue] ?? $contact['issues']['order-issue'];
    $trackOrderUrl = auth()->check()
        ? route('storefront.account.index')
        : route('login', ['intended' => route('storefront.account.index')]);
@endphp

<div class="space-y-8 lg:space-y-10" data-contact-hub>
    <section class="grid gap-8 xl:grid-cols-[1.05fr_0.95fr] xl:gap-10">
        <div class="ys-support-surface p-6 lg:p-8 xl:p-9" data-reveal>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="ys-support-kicker">Support request builder</p>
                    <h2 class="mt-3 font-serif text-3xl text-ys-ivory">Choose the issue first</h2>
                </div>
                <span class="ys-support-micro-pill">Support ticket</span>
            </div>

            <div class="mt-6 grid gap-3 sm:grid-cols-2">
                @foreach ($contact['issues'] as $issueKey => $issue)
                    <button
                        type="button"
                        class="ys-support-card-button {{ $issueKey === $selectedIssue ? 'is-active' : '' }}"
                        data-contact-issue
                        data-issue-id="{{ $issueKey }}"
                        data-issue-label="{{ $issue['label'] }}"
                        data-issue-summary="{{ $issue['summary'] }}"
                        data-issue-reference-label="{{ $issue['reference_label'] }}"
                        data-issue-reference-placeholder="{{ $issue['reference_placeholder'] }}"
                        data-issue-detail-label="{{ $issue['detail_label'] }}"
                        data-issue-detail-placeholder="{{ $issue['detail_placeholder'] }}"
                        aria-pressed="{{ $issueKey === $selectedIssue ? 'true' : 'false' }}"
                    >
                        <span class="block text-left text-sm font-semibold text-ys-ivory">{{ $issue['label'] }}</span>
                        <span class="mt-1 block text-left text-xs leading-5 text-ys-ivory/44">{{ $issue['summary'] }}</span>
                    </button>
                @endforeach
            </div>

            <div class="mt-7 rounded-[1.45rem] border border-white/8 bg-black/22 p-5 lg:p-6" id="support-request-builder" data-contact-builder>
                <p class="ys-support-info-label">Guided panel</p>
                <h3 class="mt-3 text-xl font-semibold text-ys-ivory" data-contact-issue-title>{{ $selectedIssueConfig['label'] }}</h3>
                <p class="mt-2 text-sm leading-7 text-ys-ivory/58" data-contact-issue-summary>
                    {{ $selectedIssueConfig['summary'] }}
                </p>

                <form
                    class="mt-5 space-y-4"
                    method="POST"
                    action="{{ route('storefront.support.contact.store') }}"
                    data-contact-form
                    novalidate
                >
                    @csrf
                    <input type="hidden" name="category" value="{{ $selectedIssue }}" data-contact-category>
                    <div class="hidden" aria-hidden="true">
                        <label>
                            Leave this field empty
                            <input type="text" name="website" value="" tabindex="-1" autocomplete="off">
                        </label>
                    </div>

                    <label class="ys-field">
                        <span>Your name</span>
                        <input type="text" name="name" class="ys-input" data-contact-name placeholder="Name for support reference" value="{{ old('name') }}">
                    </label>

                    <label class="ys-field">
                        <span>Reply email</span>
                        <input type="email" name="reply_email" class="ys-input" data-contact-email placeholder="Email you want support to reply to" value="{{ old('reply_email') }}">
                    </label>

                    <label class="ys-field">
                        <span data-contact-reference-label>{{ $selectedIssueConfig['reference_label'] }}</span>
                        <input
                            type="text"
                            name="reference"
                            class="ys-input"
                            data-contact-reference
                            placeholder="{{ $selectedIssueConfig['reference_placeholder'] }}"
                            value="{{ old('reference') }}"
                        >
                    </label>

                    <label class="ys-field">
                        <span data-contact-detail-label>{{ $selectedIssueConfig['detail_label'] }}</span>
                        <textarea
                            name="message"
                            class="ys-input min-h-36 resize-y py-4"
                            data-contact-details
                            placeholder="{{ $selectedIssueConfig['detail_placeholder'] }}"
                        >{{ old('message') }}</textarea>
                    </label>

                    <p class="mt-4 text-xs leading-6 text-ys-ivory/46">
                        This form sends a real support request to the Ysabelle Retail team. If sending fails, use the support contact details shown above.
                    </p>

                    <div class="mt-5">
                        <button
                            type="submit"
                            class="ys-button-primary"
                            data-contact-submit-button
                            data-idle-label="Send Support Request"
                            data-loading-label="Sending Support Request..."
                        >
                            Send Support Request
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <aside class="space-y-6">
            <section class="ys-support-surface p-6 lg:p-8 xl:p-9" data-reveal data-reveal-delay="80">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="ys-support-kicker">Quick Support Actions</p>
                        <h2 class="mt-3 font-serif text-3xl text-ys-ivory">Start with the fastest path</h2>
                    </div>
                    <span class="ys-support-micro-pill">Action first</span>
                </div>

                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                    <a href="{{ $trackOrderUrl }}" class="ys-support-card-button block">
                        <span class="block text-left text-sm font-semibold text-ys-ivory">Track an order</span>
                        <span class="mt-1 block text-left text-xs leading-5 text-ys-ivory/44">Open your order view and review placed purchases.</span>
                    </a>

                    <a href="{{ route('storefront.support.returns') }}" class="ys-support-card-button block">
                        <span class="block text-left text-sm font-semibold text-ys-ivory">Start a return</span>
                        <span class="mt-1 block text-left text-xs leading-5 text-ys-ivory/44">Go straight to return and exchange guidance.</span>
                    </a>

                    <a href="{{ route('storefront.support.size-guide') }}" class="ys-support-card-button block">
                        <span class="block text-left text-sm font-semibold text-ys-ivory">Size guide</span>
                        <span class="mt-1 block text-left text-xs leading-5 text-ys-ivory/44">Use the fit tools before you submit a support request.</span>
                    </a>

                    <button
                        type="button"
                        class="ys-support-card-button w-full text-left"
                        data-contact-quick-action
                        data-quick-issue-id="order-issue"
                        data-quick-focus="reference"
                    >
                        <span class="block text-left text-sm font-semibold text-ys-ivory">Order status help</span>
                        <span class="mt-1 block text-left text-xs leading-5 text-ys-ivory/44">Jump to the builder with the order-help path ready.</span>
                    </button>
                </div>
            </section>

            <section class="ys-support-surface p-6 lg:p-8 xl:p-9" data-reveal data-reveal-delay="140">
                <p class="ys-support-kicker">What we need from you</p>
                <ul class="mt-5 space-y-3 text-sm leading-6 text-ys-ivory/60">
                    <li class="flex items-start gap-3 rounded-[1.25rem] border border-white/8 bg-black/20 p-4">
                        <span class="mt-1 inline-flex h-2.5 w-2.5 shrink-0 rounded-full bg-ys-gold/80"></span>
                        <span>Order number or product name.</span>
                    </li>
                    <li class="flex items-start gap-3 rounded-[1.25rem] border border-white/8 bg-black/20 p-4">
                        <span class="mt-1 inline-flex h-2.5 w-2.5 shrink-0 rounded-full bg-ys-gold/80"></span>
                        <span>A reply email you actively monitor.</span>
                    </li>
                    <li class="flex items-start gap-3 rounded-[1.25rem] border border-white/8 bg-black/20 p-4">
                        <span class="mt-1 inline-flex h-2.5 w-2.5 shrink-0 rounded-full bg-ys-gold/80"></span>
                        <span>The size ordered and what went wrong.</span>
                    </li>
                    <li class="flex items-start gap-3 rounded-[1.25rem] border border-white/8 bg-black/20 p-4">
                        <span class="mt-1 inline-flex h-2.5 w-2.5 shrink-0 rounded-full bg-ys-gold/80"></span>
                        <span>Photos when the issue involves fit, condition, or the item received.</span>
                    </li>
                </ul>
            </section>
        </aside>
    </section>
</div>
