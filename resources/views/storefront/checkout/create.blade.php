@extends('layouts.storefront', ['title' => 'Checkout | Ysabelle Retail'])

@inject('media', 'App\\Support\\Storefront\\ProductMediaResolver')

@section('content')
    @php
        $selectedPaymentMethod = old('payment_method', 'cod');
        $usesSimulatedCard = $selectedPaymentMethod === 'card_simulated';
    @endphp

    <section class="ys-container pb-18 pt-10 lg:pt-14">
        <div class="grid gap-10 lg:grid-cols-[1.08fr_0.52fr] xl:gap-14">
            <div data-reveal>
                <h1 class="font-serif text-5xl text-ys-ivory">Checkout</h1>

                <form action="{{ route('storefront.checkout.store') }}" method="POST" id="checkout-form" class="mt-10 space-y-10" data-checkout-form>
                    @csrf
                    @if ($errors->any())
                        <div class="rounded-[1.4rem] border border-[#7c2727] bg-[#361010] px-5 py-4 text-sm text-[#ffd8d8]">
                            <p class="font-semibold">Checkout needs a quick fix before we can place the order.</p>
                            <ul class="mt-2 space-y-1 text-[#ffdddd]/85">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div>
                        <h2 class="font-serif text-3xl text-ys-ivory">Shipping details</h2>
                        <div class="mt-6 grid gap-5 md:grid-cols-2">
                            <label class="ys-field">
                                <span>Full name</span>
                                <input type="text" name="full_name" class="ys-input" value="{{ old('full_name', $user->name) }}">
                            </label>
                            <label class="ys-field">
                                <span>Email</span>
                                <input type="email" name="email" class="ys-input" value="{{ old('email', $user->email) }}">
                            </label>
                            <label class="ys-field">
                                <span>Phone</span>
                                <input type="text" name="phone" class="ys-input" value="{{ old('phone', $user->profile?->mobile_number ?? $user->profile?->phone) }}">
                            </label>
                            <label class="ys-field">
                                <span>City</span>
                                <input type="text" name="city" class="ys-input" value="{{ old('city') }}">
                            </label>
                            <label class="ys-field md:col-span-2">
                                <span>Address</span>
                                <input type="text" name="address" class="ys-input" value="{{ old('address') }}">
                            </label>
                            <label class="ys-field">
                                <span>Postal code</span>
                                <input type="text" name="postal_code" class="ys-input" value="{{ old('postal_code') }}">
                            </label>
                            <label class="ys-field md:col-span-2">
                                <span>Order notes (optional)</span>
                                <textarea name="order_notes" rows="4" class="ys-input min-h-34 resize-none py-4">{{ old('order_notes') }}</textarea>
                            </label>
                        </div>
                    </div>

                    <div>
                        <h2 class="font-serif text-3xl text-ys-ivory">Payment method</h2>
                        <div class="mt-6 grid gap-4 md:grid-cols-2" data-payment-options>
                            <label class="ys-payment-option {{ $selectedPaymentMethod === 'cod' ? 'ys-payment-option-active' : '' }}">
                                <input
                                    type="radio"
                                    name="payment_method"
                                    value="cod"
                                    class="sr-only"
                                    data-payment-method-option
                                    @checked($selectedPaymentMethod === 'cod')
                                >
                                <span class="block text-sm font-semibold text-ys-ivory">Cash on Delivery</span>
                                <span class="mt-1 block text-xs text-ys-ivory/45">Pay when you receive</span>
                            </label>

                            <label class="ys-payment-option {{ $usesSimulatedCard ? 'ys-payment-option-active' : '' }}">
                                <input
                                    type="radio"
                                    name="payment_method"
                                    value="card_simulated"
                                    class="sr-only"
                                    data-payment-method-option
                                    @checked($usesSimulatedCard)
                                >
                                <span class="block text-sm font-semibold text-ys-ivory">Card (simulated)</span>
                                <span class="mt-1 block text-xs text-ys-ivory/45">No real charge</span>
                            </label>
                        </div>

                        <div
                            class="mt-6 rounded-[1.6rem] border border-white/8 bg-black/25 p-5 {{ $usesSimulatedCard ? '' : 'hidden' }}"
                            data-card-payment-section
                            @if (! $usesSimulatedCard) hidden @endif
                        >
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-serif text-2xl text-ys-ivory">Simulated card details</h3>
                                    <p class="mt-2 max-w-xl text-sm leading-7 text-ys-ivory/55">
                                        Use test details only. This flow marks the payment as successful without making a real charge.
                                    </p>
                                </div>
                                <div class="rounded-2xl border border-ys-gold/20 bg-ys-gold/8 px-4 py-3 text-xs uppercase tracking-[0.24em] text-ys-gold/80">
                                    Test mode only
                                </div>
                            </div>

                            <div class="mt-5 grid gap-5 md:grid-cols-2">
                                <label class="ys-field md:col-span-2">
                                    <span>Cardholder name</span>
                                    <input
                                        type="text"
                                        name="cardholder_name"
                                        class="ys-input"
                                        value="{{ old('cardholder_name', $user->name) }}"
                                        placeholder="Ysabelle Test Card"
                                        autocomplete="cc-name"
                                    >
                                </label>

                                <label class="ys-field md:col-span-2">
                                    <span>Card number</span>
                                    <input
                                        type="text"
                                        name="card_number"
                                        class="ys-input"
                                        value="{{ old('card_number') }}"
                                        placeholder="4242 4242 4242 4242"
                                        inputmode="numeric"
                                        autocomplete="cc-number"
                                    >
                                </label>

                                <label class="ys-field">
                                    <span>Expiry</span>
                                    <input
                                        type="text"
                                        name="card_expiry"
                                        class="ys-input"
                                        value="{{ old('card_expiry') }}"
                                        placeholder="12/30"
                                        inputmode="numeric"
                                        autocomplete="cc-exp"
                                    >
                                </label>

                                <label class="ys-field">
                                    <span>Security code</span>
                                    <input
                                        type="text"
                                        name="card_cvc"
                                        class="ys-input"
                                        value="{{ old('card_cvc') }}"
                                        placeholder="123"
                                        inputmode="numeric"
                                        autocomplete="cc-csc"
                                    >
                                </label>
                            </div>

                            <div class="mt-5 rounded-[1.2rem] border border-white/8 bg-white/[0.03] px-4 py-3 text-sm text-ys-ivory/52">
                                Recommended test card: <span class="font-semibold text-ys-ivory">4242 4242 4242 4242</span>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <aside class="lg:sticky lg:top-28 lg:self-start" data-reveal>
                <div class="rounded-[1.8rem] border border-white/7 bg-ys-panel/95 p-6 shadow-[0_22px_70px_rgba(0,0,0,0.4)]">
                    <h2 class="font-serif text-3xl text-ys-ivory">Order summary</h2>

                    <div class="mt-6 space-y-4 border-b border-white/6 pb-5">
                        @foreach ($summary['items'] as $item)
                            <div class="flex items-center gap-3">
                                <x-storefront.product-media
                                    :image-url="$media->imageUrlFor($item->variant->product)"
                                    :alt="$media->altTextFor($item->variant->product)"
                                    :title="$item->variant->product->name"
                                    :eyebrow="$item->variant->product->category?->name ?? 'Collection'"
                                    class="h-14 w-14 rounded-xl border border-white/6"
                                    fallback-class="p-3"
                                />
                                <div class="flex-1">
                                    <p class="text-sm font-semibold text-ys-ivory">{{ $item->variant->product->name }}</p>
                                    <p class="text-xs text-ys-ivory/42">{{ $item->variant->name }} &middot; Qty {{ $item->quantity }}</p>
                                </div>
                                <p class="text-sm font-semibold text-ys-ivory">&#8369;{{ number_format((float) $item->line_total, 0) }}</p>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-5 space-y-4 text-sm">
                        <div class="flex items-center justify-between text-ys-ivory/55">
                            <span>Subtotal</span>
                            <span>&#8369;{{ number_format($summary['subtotal'], 0) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-ys-ivory/55">
                            <span>Shipping</span>
                            <span class="text-[#7dcf8e]">{!! $summary['shipping'] == 0 ? 'Free' : '&#8369;'.number_format($summary['shipping'], 0) !!}</span>
                        </div>
                        <div class="flex items-center justify-between border-t border-white/6 pt-4 text-lg font-semibold text-ys-gold">
                            <span>Total</span>
                            <span>&#8369;{{ number_format($summary['total'], 0) }}</span>
                        </div>
                    </div>

                    <button
                        type="submit"
                        form="checkout-form"
                        class="ys-button-primary mt-8 w-full justify-center"
                        data-checkout-submit
                        data-default-label="Place order"
                        data-card-label="Pay Now"
                        data-total-label="&#8369;{{ number_format($summary['total'], 0) }}"
                    >
                        {{ $usesSimulatedCard ? 'Pay Now' : 'Place order' }} &middot; &#8369;{{ number_format($summary['total'], 0) }}
                    </button>
                </div>
            </aside>
        </div>
    </section>
@endsection
