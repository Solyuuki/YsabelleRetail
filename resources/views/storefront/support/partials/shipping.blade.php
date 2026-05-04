@php
    $shipping = $page['view_data'];
    $defaultLocation = $shipping['locations'][0];
@endphp

<div class="space-y-8 lg:space-y-10">
    <section class="grid gap-8 xl:grid-cols-[0.94fr_1.06fr] xl:gap-10">
        <div class="ys-support-surface p-6 lg:p-8 xl:p-9" data-reveal>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="ys-support-kicker">Delivery estimator</p>
                    <h2 class="mt-3 font-serif text-3xl text-ys-ivory">Set expectations before checkout</h2>
                </div>
                <span class="ys-support-micro-pill">Support estimate</span>
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <div class="ys-support-highlight-card">
                    <p class="ys-support-info-label">Free shipping</p>
                    <p class="mt-2 text-3xl font-semibold text-ys-ivory">PHP 5,000+</p>
                    <p class="mt-2 text-sm leading-6 text-ys-ivory/58">Cart subtotal threshold for free local delivery.</p>
                </div>
                <div class="ys-support-highlight-card">
                    <p class="ys-support-info-label">Shipping fee</p>
                    <p class="mt-2 text-3xl font-semibold text-ys-ivory">PHP 350</p>
                    <p class="mt-2 text-sm leading-6 text-ys-ivory/58">Applied below the free-shipping threshold.</p>
                </div>
            </div>

            <div class="mt-7" data-shipping-estimator>
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-ys-ivory/42">Choose a destination area</p>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach ($shipping['locations'] as $location)
                        <button
                            type="button"
                            class="ys-support-card-button {{ $loop->first ? 'is-active' : '' }}"
                            data-shipping-location
                            data-location-id="{{ $location['id'] }}"
                            data-location-window="{{ $location['window'] }}"
                            data-location-note="{{ $location['note'] }}"
                            aria-pressed="{{ $loop->first ? 'true' : 'false' }}"
                        >
                            <span class="block text-left text-sm font-semibold text-ys-ivory">{{ $location['label'] }}</span>
                            <span class="mt-1 block text-left text-xs leading-5 text-ys-ivory/44">{{ $location['window'] }}</span>
                        </button>
                    @endforeach
                </div>

                <div class="mt-6 rounded-[1.45rem] border border-ys-gold/22 bg-ys-gold/8 p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-ys-gold/78">Estimated after shipment</p>
                    <h3 class="mt-3 text-2xl font-semibold text-ys-ivory" data-shipping-window>{{ $defaultLocation['window'] }}</h3>
                    <p class="mt-3 text-sm leading-7 text-ys-ivory/62" data-shipping-note>{{ $defaultLocation['note'] }}</p>
                    <p class="mt-4 text-xs uppercase tracking-[0.22em] text-ys-ivory/40">No exact date guarantee until the carrier is actively moving the parcel.</p>
                </div>
            </div>
        </div>

        <div class="ys-support-surface p-6 lg:p-8 xl:p-9" data-reveal data-reveal-delay="90">
            <p class="ys-support-kicker">Shipping timeline</p>
            <h2 class="mt-3 font-serif text-3xl text-ys-ivory">What shoppers can expect next</h2>

            <div class="mt-6 grid gap-5 md:grid-cols-2">
                @foreach ($shipping['timeline'] as $step)
                    <article class="ys-support-step-card">
                        <span class="ys-support-step-index">0{{ $loop->iteration }}</span>
                        <h3 class="mt-4 text-xl font-semibold text-ys-ivory">{{ $step['title'] }}</h3>
                        <p class="mt-2 text-sm leading-7 text-ys-ivory/58">{{ $step['detail'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="ys-support-surface p-6 lg:p-8 xl:p-9" data-reveal data-reveal-delay="150">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="ys-support-kicker">Trust checks</p>
                <h2 class="mt-3 font-serif text-3xl text-ys-ivory">Three reminders that reduce delivery friction</h2>
            </div>
            <span class="ys-support-micro-pill">Buyer-friendly</span>
        </div>

        <div class="mt-6 grid gap-5 md:grid-cols-3">
            @foreach ($shipping['trust_cards'] as $card)
                <article class="ys-support-info-card min-h-[11rem]">
                    <h3 class="text-lg font-semibold text-ys-ivory">{{ $card['title'] }}</h3>
                    <p class="mt-3 text-sm leading-7 text-ys-ivory/58">{{ $card['detail'] }}</p>
                </article>
            @endforeach
        </div>
    </section>
</div>
