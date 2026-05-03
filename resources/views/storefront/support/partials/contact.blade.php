@php
    $contact = $page['view_data'];
@endphp

<div class="space-y-8 lg:space-y-10" data-contact-hub>
    <section class="grid gap-8 xl:grid-cols-[1.05fr_0.95fr] xl:gap-10">
        <div class="ys-support-surface p-6 lg:p-8 xl:p-9" data-reveal>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="ys-support-kicker">Support request builder</p>
                    <h2 class="mt-3 font-serif text-3xl text-ys-ivory">Choose the issue first</h2>
                </div>
                <span class="ys-support-micro-pill">Email-driven</span>
            </div>

            <div class="mt-6 grid gap-3 sm:grid-cols-2">
                @foreach ($contact['issues'] as $issueKey => $issue)
                    <button
                        type="button"
                        class="ys-support-card-button {{ $issueKey === 'order-issue' ? 'is-active' : '' }}"
                        data-contact-issue
                        data-issue-id="{{ $issueKey }}"
                        data-issue-label="{{ $issue['label'] }}"
                        data-issue-summary="{{ $issue['summary'] }}"
                        aria-pressed="{{ $issueKey === 'order-issue' ? 'true' : 'false' }}"
                    >
                        <span class="block text-left text-sm font-semibold text-ys-ivory">{{ $issue['label'] }}</span>
                        <span class="mt-1 block text-left text-xs leading-5 text-ys-ivory/44">{{ $issue['summary'] }}</span>
                    </button>
                @endforeach
            </div>

            <div class="mt-7 rounded-[1.45rem] border border-white/8 bg-black/22 p-5 lg:p-6">
                <p class="ys-support-info-label">Guided panel</p>
                <h3 class="mt-3 text-xl font-semibold text-ys-ivory" data-contact-issue-title>Order Issue</h3>
                <p class="mt-2 text-sm leading-7 text-ys-ivory/58" data-contact-issue-summary>
                    Use this for delivery concerns, checkout follow-up, or order detail mismatches.
                </p>

                <form class="mt-5 space-y-4" onsubmit="return false;">
                    <label class="ys-field">
                        <span>Your name</span>
                        <input type="text" class="ys-input" data-contact-name placeholder="Name for support reference">
                    </label>

                    <label class="ys-field">
                        <span>Reply email</span>
                        <input type="email" class="ys-input" data-contact-email placeholder="Email you want support to reply to">
                    </label>

                    <label class="ys-field">
                        <span data-contact-reference-label>Order number</span>
                        <input type="text" class="ys-input" data-contact-reference placeholder="Example: YS-10425">
                    </label>

                    <label class="ys-field">
                        <span data-contact-detail-label>Issue details</span>
                        <textarea class="ys-input min-h-36 resize-y py-4" data-contact-details placeholder="Tell support what happened, what you expected, and any helpful context."></textarea>
                    </label>
                </form>

                <p class="mt-4 text-xs leading-6 text-ys-ivory/46">
                    This page does not currently process a live contact form submission. It prepares a real email draft in your mail app instead of pretending to submit a ticket.
                </p>

                <div class="mt-5 flex flex-wrap gap-3">
                    <a href="{{ $supportContact['general_mailto'] }}" class="ys-button-primary" data-contact-email-link>
                        Open Email Draft
                    </a>
                    <a href="{{ $supportContact['phone_href'] }}" class="ys-button-secondary">
                        Call Support
                    </a>
                </div>
            </div>
        </div>

        <aside class="space-y-6">
            <section class="ys-support-surface p-6 lg:p-8 xl:p-9" data-reveal data-reveal-delay="80">
                <p class="ys-support-kicker">Direct support details</p>
                <div class="mt-5 grid gap-4">
                    <div class="ys-support-info-card">
                        <p class="ys-support-info-label">Email</p>
                        <a href="{{ $supportContact['general_mailto'] }}" class="ys-support-inline-link">{{ $supportContact['email'] }}</a>
                    </div>
                    <div class="ys-support-info-card">
                        <p class="ys-support-info-label">Phone</p>
                        <a href="{{ $supportContact['phone_href'] }}" class="ys-support-inline-link">{{ $supportContact['phone'] }}</a>
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
