@extends('layouts.storefront', ['title' => 'Privacy Policy | Ysabelle Retail'])

@section('content')
    <section class="ys-container pb-20 pt-12 lg:pb-24 lg:pt-16">
        <div class="mx-auto max-w-4xl">
            <div class="rounded-[2rem] border border-white/8 bg-white/[0.03] p-6 shadow-[0_24px_70px_rgba(0,0,0,0.28)] lg:p-8 xl:p-10" data-reveal>
                <p class="text-xs font-semibold uppercase tracking-[0.34em] text-ys-gold/84">Legal</p>
                <h1 class="mt-4 font-serif text-4xl text-ys-ivory sm:text-5xl">Privacy Policy</h1>
                <p class="mt-5 max-w-3xl text-sm leading-7 text-ys-ivory/60">
                    This page explains the general store-policy approach Ysabelle Retail uses when handling customer information for browsing, account access, orders, and support communication.
                </p>
                <p class="mt-4 rounded-[1.35rem] border border-white/8 bg-black/18 px-5 py-4 text-sm leading-7 text-ys-ivory/56">
                    This is general store privacy content for the current site experience. It should be read together with the information you submit during account, checkout, and support flows.
                </p>
            </div>

            <div class="mt-8 grid gap-5 md:grid-cols-2">
                <article class="rounded-[1.7rem] border border-white/7 bg-ys-panel/85 p-6" data-reveal>
                    <h2 class="text-lg font-semibold text-ys-ivory">Information we collect</h2>
                    <p class="mt-3 text-sm leading-7 text-ys-ivory/58">We may collect details you provide directly, such as your name, email address, account credentials, order information, and the messages you send through support forms.</p>
                </article>

                <article class="rounded-[1.7rem] border border-white/7 bg-ys-panel/85 p-6" data-reveal data-reveal-delay="40">
                    <h2 class="text-lg font-semibold text-ys-ivory">How we use information</h2>
                    <p class="mt-3 text-sm leading-7 text-ys-ivory/58">Store information is used to support account access, order handling, delivery communication, checkout operations, customer support, and the day-to-day operation of the shopping experience.</p>
                </article>

                <article class="rounded-[1.7rem] border border-white/7 bg-ys-panel/85 p-6" data-reveal data-reveal-delay="80">
                    <h2 class="text-lg font-semibold text-ys-ivory">Orders and support</h2>
                    <p class="mt-3 text-sm leading-7 text-ys-ivory/58">Order references, product details, and support messages help the team verify concerns and respond with the right context. Support requests should use accurate contact information to avoid delays.</p>
                </article>

                <article class="rounded-[1.7rem] border border-white/7 bg-ys-panel/85 p-6" data-reveal data-reveal-delay="120">
                    <h2 class="text-lg font-semibold text-ys-ivory">Account security</h2>
                    <p class="mt-3 text-sm leading-7 text-ys-ivory/58">Customers are responsible for keeping account credentials private. Shared devices, reused passwords, and unsecured email access can affect the safety of account-linked store activity.</p>
                </article>

                <article class="rounded-[1.7rem] border border-white/7 bg-ys-panel/85 p-6" data-reveal data-reveal-delay="160">
                    <h2 class="text-lg font-semibold text-ys-ivory">Operational limits</h2>
                    <p class="mt-3 text-sm leading-7 text-ys-ivory/58">Some information may be retained as needed for store operations, order history, support continuity, or auditability. The exact handling depends on the transaction or support context involved.</p>
                </article>

                <article class="rounded-[1.7rem] border border-white/7 bg-ys-panel/85 p-6" data-reveal data-reveal-delay="200">
                    <h2 class="text-lg font-semibold text-ys-ivory">Contact</h2>
                    <p class="mt-3 text-sm leading-7 text-ys-ivory/58">If you need clarification about privacy-related store communication or a support request, use the contact page so the team can review the question in the same support workflow.</p>
                    <a href="{{ route('storefront.support.contact') }}" class="mt-4 inline-flex text-sm font-semibold text-ys-gold transition hover:text-ys-ivory focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ys-gold/55 focus-visible:ring-offset-2 focus-visible:ring-offset-ys-ink">
                        Open Support Contact
                    </a>
                </article>
            </div>
        </div>
    </section>
@endsection
