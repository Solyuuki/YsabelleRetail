@extends('layouts.storefront', ['title' => 'Shopping Bag | Ysabelle Retail'])

@inject('media', 'App\\Support\\Storefront\\ProductMediaResolver')

@section('content')
    <section class="ys-container pb-18 pt-10 lg:pt-14">
        @if ($summary['is_empty'])
            <div class="mx-auto max-w-3xl rounded-[2rem] border border-white/7 bg-ys-panel/60 px-8 py-24 text-center" data-reveal>
                <span class="mx-auto inline-flex h-20 w-20 items-center justify-center rounded-full border border-white/10 bg-white/[0.02] text-ys-ivory/60">
                    <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path d="M7 8V6a5 5 0 0 1 10 0v2" stroke-linecap="round" />
                        <path d="M5.5 8.5h13l-.9 10.5H6.4L5.5 8.5Z" />
                    </svg>
                </span>
                <h1 class="mt-8 font-serif text-5xl text-ys-ivory">Your cart is empty</h1>
                <p class="mx-auto mt-4 max-w-md text-sm leading-7 text-ys-ivory/48">
                    Discover the collection and find your next pair.
                </p>
                <a href="{{ route('storefront.shop') }}" class="ys-button-primary mt-8">Shop now</a>
            </div>
        @else
            <div class="grid gap-10 lg:grid-cols-[1.15fr_0.55fr] xl:gap-14">
                <div>
                    <h1 class="font-serif text-5xl text-ys-ivory">Shopping bag</h1>
                    <p class="mt-3 text-sm text-ys-ivory/45">{{ $summary['item_count'] }} item{{ $summary['item_count'] > 1 ? 's' : '' }}</p>

                    <div class="mt-8 space-y-4">
                        @foreach ($summary['items'] as $item)
                            <article class="rounded-[1.6rem] border border-white/7 bg-ys-panel/80 p-4 sm:p-5" data-reveal>
                                <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                                    <div class="overflow-hidden rounded-2xl border border-white/6 bg-black">
                                        <x-storefront.product-media
                                            :image-url="$media->imageUrlFor($item->variant->product)"
                                            :alt="$media->altTextFor($item->variant->product)"
                                            :title="$item->variant->product->name"
                                            :eyebrow="$item->variant->product->category?->name ?? 'Collection'"
                                            class="h-20 w-20"
                                            fallback-class="p-3"
                                        />
                                    </div>

                                    <div class="flex-1">
                                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <h2 class="text-lg font-semibold text-ys-ivory">{{ $item->variant->product->name }}</h2>
                                                <p class="mt-1 text-sm text-ys-ivory/48">{{ $item->variant->name }} &middot; {{ $item->variant->option_values['color'] ?? 'Signature finish' }}</p>
                                            </div>
                                            <p class="text-lg font-semibold text-ys-ivory">&#8369;{{ number_format((float) $item->line_total, 0) }}</p>
                                        </div>

                                        <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
                                            <form method="POST" action="{{ route('storefront.cart.items.update', $item) }}" class="inline-flex items-center rounded-2xl border border-white/10 bg-white/[0.02] p-1" data-cart-quantity-form>
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="quantity" value="{{ $item->quantity }}" data-cart-quantity-input>
                                                <button type="button" class="ys-quantity-button" data-cart-step="-1">&minus;</button>
                                                <span class="inline-flex min-w-12 items-center justify-center text-sm font-semibold text-ys-ivory" data-cart-quantity-display>{{ $item->quantity }}</span>
                                                <button type="button" class="ys-quantity-button" data-cart-step="1">+</button>
                                            </form>

                                            <form method="POST" action="{{ route('storefront.cart.items.destroy', $item) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-sm font-medium text-ys-ivory/45 transition hover:text-[#e17373]">Remove</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>

                <aside class="lg:sticky lg:top-28 lg:self-start" data-reveal>
                    <div class="rounded-[1.8rem] border border-white/7 bg-ys-panel/95 p-6 shadow-[0_22px_70px_rgba(0,0,0,0.4)]">
                        <h2 class="font-serif text-3xl text-ys-ivory">Order summary</h2>
                        <div class="mt-7 space-y-4 text-sm">
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

                        <div class="mt-8 space-y-3">
                            @auth
                                <a href="{{ route('storefront.checkout.create') }}" class="ys-button-primary w-full justify-center">Checkout</a>
                            @else
                                <a href="{{ route('login') }}" class="ys-button-primary w-full justify-center">Sign in to checkout</a>
                            @endauth
                            <a href="{{ route('storefront.shop') }}" class="ys-button-ghost w-full justify-center">Continue shopping</a>
                        </div>
                    </div>
                </aside>
            </div>
        @endif
    </section>
@endsection
