@extends('layouts.app', ['title' => 'Storefront Cart'])

@section('content')
    <div class="rounded-3xl border border-white/10 bg-white/5 p-8">
        <p class="text-sm uppercase tracking-[0.3em] text-amber-300">Cart Domain</p>
        <h1 class="mt-3 text-3xl font-semibold text-white">Cart structure is ready for future customer checkout flows.</h1>
        <p class="mt-4 max-w-3xl text-stone-300">
            Cart tables, models, and route/controller boundaries exist, but add-to-cart, pricing recalculation, and promo application logic are not implemented yet.
        </p>
    </div>
@endsection
