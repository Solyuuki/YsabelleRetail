@php
    $contact = $page['view_data'];
    $selectedIssue = old('category', 'order-issue');
    $selectedIssueConfig = $contact['issues'][$selectedIssue] ?? $contact['issues']['order-issue'];
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

            <div class="mt-7 rounded-[1.45rem] border border-white/8 bg-black/22 p-5 lg:p-6">
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
                        This form sends a real support request to the Ysabelle Retail team. If sending fails, you can still use the email fallback shown on this page.
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
                <p class="ys-support-kicker">Direct support details</p>
                <div class="mt-5 grid gap-4">
                    <div class="ys-support-info-card">
                        <p class="ys-support-info-label">Email</p>
                        <a href="{{ $supportContact['general_mailto'] }}" class="ys-support-inline-link" data-contact-fallback-email-link>{{ $supportContact['email'] }}</a>
                    </div>
                    <div class="ys-support-info-card">
                        <p class="ys-support-info-label">Phone</p>
                        <a href="{{ $supportContact['phone_href'] }}" class="ys-support-inline-link" data-contact-call-link>{{ $supportContact['phone'] }}</a>
                    </div>
                    <div class="ys-support-info-card">
                        <p class="ys-support-info-label">Best to include</p>
                        <p class="text-sm leading-7 text-ys-ivory/60">Order number, product name, your usual size, and photos if the issue is visual or condition-related.</p>
                    </div>
                </div>
            </section>

            <section class="ys-support-surface ys-support-location-card p-6 lg:p-8 xl:p-9" data-reveal data-reveal-delay="140">
                <p class="ys-support-kicker">BGC support hub</p>
                <h2 class="mt-3 font-serif text-3xl text-ys-ivory">Bonifacio Global City, Taguig</h2>
                <p class="mt-4 max-w-xl text-sm leading-7 text-ys-ivory/60">{{ $supportContact['address'] }}</p>
                <p class="mt-3 text-xs uppercase tracking-[0.22em] text-ys-ivory/36">Simulated support point around BGC for storefront contact guidance.</p>
            </section>
        </aside>
    </section>
</div>
