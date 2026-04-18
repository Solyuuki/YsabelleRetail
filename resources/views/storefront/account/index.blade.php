@extends('layouts.storefront', ['title' => 'My Account | Ysabelle Retail'])

@section('content')
    <section class="ys-container pb-18 pt-10 lg:pt-14">
        @if ($latestOrderNumber)
            <div class="mb-8 rounded-2xl border border-[#0d5a2e] bg-[#0a2a16] px-5 py-4 text-sm text-[#d5f2dd]" data-reveal>
                <p class="font-semibold">Order {{ $latestOrderNumber }} confirmed</p>
                <p class="mt-1 text-[#a3d3b2]">We'll send updates to your email.</p>
            </div>
        @endif

        <p class="text-xs font-semibold uppercase tracking-[0.35em] text-ys-gold/85">My Account</p>
        <h1 class="mt-4 font-serif text-5xl text-ys-ivory lg:text-6xl">Hello, {{ $user->name }}</h1>

        <div class="mt-12">
            <h2 class="font-serif text-3xl text-ys-ivory">Your orders</h2>

            @if ($orders->isEmpty())
                <div class="mt-6 rounded-[1.8rem] border border-dashed border-white/12 bg-white/[0.02] px-6 py-16 text-center">
                    <p class="font-serif text-4xl text-ys-ivory">No orders yet.</p>
                    <p class="mx-auto mt-4 max-w-md text-sm leading-7 text-ys-ivory/48">Once checkout is completed, your order history will appear here with the same premium card treatment shown in the reference.</p>
                    <a href="{{ route('storefront.shop') }}" class="ys-button-primary mt-8">Start shopping</a>
                </div>
            @else
                <div class="mt-6 space-y-4">
                    @foreach ($orders as $order)
                        <article class="rounded-[1.6rem] border border-white/7 bg-ys-panel/80 p-5" data-reveal>
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <p class="text-sm text-ys-ivory/42">{{ optional($order->placed_at)->format('m/d/Y') ?? $order->created_at->format('m/d/Y') }}</p>
                                    <p class="mt-2 text-sm font-semibold tracking-[0.22em] text-ys-ivory/72">{{ $order->order_number }}</p>
                                </div>

                                <div class="flex items-center gap-4">
                                    <span class="rounded-full bg-white/[0.06] px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-ys-ivory/60">{{ $order->status }}</span>
                                    <span class="text-lg font-semibold text-ys-ivory">&#8369;{{ number_format((float) $order->grand_total, 0) }}</span>
                                </div>
                            </div>

                            <div class="mt-5 space-y-3">
                                @foreach ($order->items as $item)
                                    <div class="flex items-center gap-4">
                                        <x-storefront.product-media
                                            :image-url="$item->metadata['product_image_url'] ?? null"
                                            :alt="$item->metadata['product_image_alt'] ?? $item->product_name"
                                            :title="$item->product_name"
                                            eyebrow="Order Item"
                                            class="h-16 w-16 rounded-xl border border-white/6"
                                            fallback-class="p-3"
                                        />
                                        <div>
                                            <p class="text-sm font-semibold text-ys-ivory">{{ $item->product_name }}</p>
                                            <p class="text-xs text-ys-ivory/42">{{ $item->variant_name }} &middot; Qty {{ $item->quantity }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection
