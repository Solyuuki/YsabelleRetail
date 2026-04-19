@extends('layouts.storefront', ['title' => 'Checkout | Ysabelle Retail'])

@inject('media', 'App\\Support\\Storefront\\ProductMediaResolver')

@section('content')
    <section class="ys-container pb-18 pt-10 lg:pt-14">
        <div class="grid gap-10 lg:grid-cols-[1.08fr_0.52fr] xl:gap-14">
            <div data-reveal>
                <h1 class="font-serif text-5xl text-ys-ivory">Checkout</h1>

                <form action="{{ route('storefront.checkout.store') }}" method="POST" id="checkout-form" class="mt-10 space-y-10">
                    @csrf
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
                            <label class="ys-payment-option {{ old('payment_method', 'cod') === 'cod' ? 'ys-payment-option-active' : '' }}">
                                <input type="radio" name="payment_method" value="cod" class="sr-only" @checked(old('payment_method', 'cod') === 'cod')>
                                <span class="block text-sm font-semibold text-ys-ivory">Cash on Delivery</span>
                                <span class="mt-1 block text-xs text-ys-ivory/45">Pay when you receive</span>
                            </label>

                            <label class="ys-payment-option {{ old('payment_method') === 'card' ? 'ys-payment-option-active' : '' }}">
                                <input type="radio" name="payment_method" value="card" class="sr-only" @checked(old('payment_method') === 'card')>
                                <span class="block text-sm font-semibold text-ys-ivory">Card (simulated)</span>
                                <span class="mt-1 block text-xs text-ys-ivory/45">No real charge</span>
                            </label>
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

                    <button type="submit" form="checkout-form" class="ys-button-primary mt-8 w-full justify-center">
                        Place order &middot; &#8369;{{ number_format($summary['total'], 0) }}
                    </button>
                </div>
            </aside>
        </div>
    </section>
@endsection
