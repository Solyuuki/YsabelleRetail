@extends('layouts.storefront', ['title' => 'Terms of Use | Ysabelle Retail'])

@section('content')
    <section class="ys-container pb-20 pt-12 lg:pb-24 lg:pt-16">
        <div class="mx-auto max-w-4xl">
            <div class="rounded-[2rem] border border-white/8 bg-white/[0.03] p-6 shadow-[0_24px_70px_rgba(0,0,0,0.28)] lg:p-8 xl:p-10" data-reveal>
                <p class="text-xs font-semibold uppercase tracking-[0.34em] text-ys-gold/84">Legal</p>
                <h1 class="mt-4 font-serif text-4xl text-ys-ivory sm:text-5xl">Terms of Use</h1>
                <p class="mt-5 max-w-3xl text-sm leading-7 text-ys-ivory/60">
                    These terms describe the general store policies for using the Ysabelle Retail website, browsing products,
                    creating an account, and placing orders. They are presented as general commerce guidance for this store experience.
                </p>
                <p class="mt-4 rounded-[1.35rem] border border-white/8 bg-black/18 px-5 py-4 text-sm leading-7 text-ys-ivory/56">
                    This page is informational store policy content. Specific order, payment, delivery, and support outcomes still depend on the details shown during checkout and the support review process.
                </p>
            </div>

            <div class="mt-8 grid gap-5 md:grid-cols-2">
                <article class="rounded-[1.7rem] border border-white/7 bg-ys-panel/85 p-6" data-reveal>
                    <h2 class="text-lg font-semibold text-ys-ivory">Account use</h2>
                    <p class="mt-3 text-sm leading-7 text-ys-ivory/58">Use accurate account details, protect your sign-in credentials, and keep your contact information current so order and support communication stays reliable.</p>
                </article>

                <article class="rounded-[1.7rem] border border-white/7 bg-ys-panel/85 p-6" data-reveal data-reveal-delay="40">
                    <h2 class="text-lg font-semibold text-ys-ivory">Orders and purchases</h2>
                    <p class="mt-3 text-sm leading-7 text-ys-ivory/58">Orders are subject to product availability, checkout review, and payment confirmation. The final checkout summary remains the best reference for totals, delivery details, and the order information you submit.</p>
                </article>

                <article class="rounded-[1.7rem] border border-white/7 bg-ys-panel/85 p-6" data-reveal data-reveal-delay="80">
                    <h2 class="text-lg font-semibold text-ys-ivory">Product information</h2>
                    <p class="mt-3 text-sm leading-7 text-ys-ivory/58">We present product descriptions, images, size guidance, and pricing to help shoppers make informed decisions, but fit, color perception, and availability can vary by item and timing.</p>
                </article>

                <article class="rounded-[1.7rem] border border-white/7 bg-ys-panel/85 p-6" data-reveal data-reveal-delay="120">
                    <h2 class="text-lg font-semibold text-ys-ivory">Returns and support</h2>
                    <p class="mt-3 text-sm leading-7 text-ys-ivory/58">Return and support requests should follow the guidance published on the store support pages. Review windows, item condition, and order context matter when the support team evaluates a request.</p>
                </article>

                <article class="rounded-[1.7rem] border border-white/7 bg-ys-panel/85 p-6" data-reveal data-reveal-delay="160">
                    <h2 class="text-lg font-semibold text-ys-ivory">Acceptable use</h2>
                    <p class="mt-3 text-sm leading-7 text-ys-ivory/58">Do not use the site in a way that disrupts store operations, attempts unauthorized access, abuses support channels, or misuses other customers&rsquo; account or order information.</p>
                </article>

                <article class="rounded-[1.7rem] border border-white/7 bg-ys-panel/85 p-6" data-reveal data-reveal-delay="200">
                    <h2 class="text-lg font-semibold text-ys-ivory">Contact</h2>
                    <p class="mt-3 text-sm leading-7 text-ys-ivory/58">For store-policy questions, order follow-up, or support concerns, use the contact path on the support page so your request includes the right context for review.</p>
                    <a href="{{ route('storefront.support.contact') }}" class="mt-4 inline-flex text-sm font-semibold text-ys-gold transition hover:text-ys-ivory focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ys-gold/55 focus-visible:ring-offset-2 focus-visible:ring-offset-ys-ink">
                        Open Support Contact
                    </a>
                </article>
            </div>
        </div>
    </section>
@endsection
