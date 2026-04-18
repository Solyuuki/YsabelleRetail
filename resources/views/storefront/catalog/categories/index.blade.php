@extends('layouts.storefront', ['title' => 'Categories | Ysabelle Retail'])

@section('content')
    <section class="ys-container pb-18 pt-10 lg:pt-14">
        <x-storefront.section-heading
            eyebrow="Curated Categories"
            title="Explore by collection"
            description="The premium storefront exposes categories as clean, reusable retail entry points."
        />

        <div class="mt-10 grid gap-5 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($categories as $category)
                <a href="{{ route('storefront.shop', ['category' => $category->slug]) }}" class="rounded-[1.8rem] border border-white/7 bg-white/[0.02] p-6 transition duration-500 hover:border-ys-gold/30 hover:bg-white/[0.035]" data-reveal>
                    <p class="text-xs uppercase tracking-[0.3em] text-ys-gold/85">Collection</p>
                    <h2 class="mt-4 font-serif text-3xl text-ys-ivory">{{ $category->name }}</h2>
                    <p class="mt-4 text-sm leading-7 text-ys-ivory/50">{{ $category->description }}</p>
                </a>
            @endforeach
        </div>
    </section>
@endsection
