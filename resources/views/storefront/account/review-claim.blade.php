@extends('layouts.storefront', ['title' => 'Claim Walk-in Purchase | Ysabelle Retail'])

@section('content')
    <section class="ys-container pb-18 pt-10 lg:pt-14">
        <p class="text-xs font-semibold uppercase tracking-[0.35em] text-ys-gold/85">Verified Reviews</p>
        <div class="mt-4 max-w-3xl">
            <h1 class="font-serif text-5xl text-ys-ivory lg:text-6xl">Claim your walk-in purchase</h1>
            <p class="mt-4 text-sm leading-7 text-ys-ivory/58">
                Link your paid in-store purchase to a real Ysabelle Retail account so you can leave verified reviews for the products you bought.
            </p>
        </div>

        @if ($errors->any())
            <div class="mt-8 rounded-[1.5rem] border border-[#7a2f2f] bg-[#2b1111] px-5 py-4 text-sm text-[#ffdede]">
                <ul class="space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mt-10 rounded-[1.8rem] border border-white/7 bg-ys-panel/80 p-6 lg:p-8">
            @if ($status === 'invalid')
                <p class="font-serif text-3xl text-ys-ivory">This claim link is not available.</p>
                <p class="mt-4 max-w-2xl text-sm leading-7 text-ys-ivory/52">The purchase claim could not be found. Use the latest email from Ysabelle Retail or contact support if you still need help linking your walk-in purchase.</p>
            @elseif ($status === 'expired')
                <p class="font-serif text-3xl text-ys-ivory">This claim link has expired.</p>
                <p class="mt-4 max-w-2xl text-sm leading-7 text-ys-ivory/52">For security, walk-in review claim links are time-limited. Contact Ysabelle Retail support if you still need help linking this purchase.</p>
            @elseif ($status === 'claimed')
                <p class="font-serif text-3xl text-ys-ivory">This purchase has already been claimed.</p>
                <p class="mt-4 max-w-2xl text-sm leading-7 text-ys-ivory/52">The walk-in purchase is already linked to a customer account, so the same claim link cannot be used again.</p>
                @auth
                    <a href="{{ route('storefront.account.index') }}" class="ys-button-primary mt-8">Open my account</a>
                @endauth
            @elseif ($status === 'unavailable')
                <p class="font-serif text-3xl text-ys-ivory">This purchase is not eligible right now.</p>
                <p class="mt-4 max-w-2xl text-sm leading-7 text-ys-ivory/52">Only completed paid walk-in purchases with an email address can be claimed for verified reviews.</p>
            @else
                @php($order = $claim->order)
                <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-sm text-ys-ivory/42">{{ optional($order->placed_at)->format('F j, Y g:i A') }}</p>
                        <p class="mt-2 text-sm font-semibold tracking-[0.22em] text-ys-ivory/72">{{ $order->order_number }}</p>
                        <p class="mt-4 text-sm leading-7 text-ys-ivory/58">
                            Claim email: {{ $maskedClaimEmail }}
                        </p>
                    </div>
                    <div class="rounded-full bg-white/[0.06] px-4 py-2 text-xs font-semibold uppercase tracking-[0.24em] text-emerald-200">
                        Completed & Paid
                    </div>
                </div>

                <div class="mt-8 space-y-3">
                    @foreach ($order->items as $item)
                        <div class="rounded-[1.2rem] border border-white/6 bg-white/[0.03] px-4 py-4">
                            <p class="text-sm font-semibold text-ys-ivory">{{ $item->product_name }}</p>
                            <p class="mt-1 text-xs text-ys-ivory/42">{{ $item->variant_name ?: 'Variant recorded' }} · SKU {{ $item->sku ?: 'Recorded on order' }} · Qty {{ $item->quantity }}</p>
                        </div>
                    @endforeach
                </div>

                @if ($status === 'guest')
                    <p class="mt-8 max-w-2xl text-sm leading-7 text-ys-ivory/52">Sign in with the same email address that received this claim link, or create a new customer account with that exact email address first.</p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <a href="{{ $loginUrl }}" class="ys-button-primary">Sign in to claim</a>
                        <a href="{{ $registerUrl }}" class="ys-button-secondary">Create account with this email</a>
                    </div>
                @elseif ($status === 'mismatch')
                    <p class="mt-8 max-w-2xl text-sm leading-7 text-ys-ivory/52">You are signed in as <strong>{{ auth()->user()->email }}</strong>. This claim only works with the email address that received the purchase email.</p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <a href="{{ $loginUrl }}" class="ys-button-primary">Use a different account</a>
                        <a href="{{ route('storefront.account.index') }}" class="ys-button-secondary">Keep current account</a>
                    </div>
                @elseif ($status === 'ready')
                    <p class="mt-8 max-w-2xl text-sm leading-7 text-ys-ivory/52">You are signed in with the correct email. Confirm the claim to link this walk-in purchase to your account and unlock verified reviews for the products above.</p>
                    <form action="{{ route('storefront.account.review-claims.store', ['token' => $token]) }}" method="POST" class="mt-8">
                        @csrf
                        <button type="submit" class="ys-button-primary">Confirm purchase claim</button>
                    </form>
                @endif
            @endif
        </div>
    </section>
@endsection
