@extends('layouts.storefront', ['title' => $category->name])

@section('content')
    <section class="ys-container pb-18 pt-10 lg:pt-14">
        <div class="rounded-[2rem] border border-white/7 bg-ys-panel/70 p-8" data-reveal>
            <x-storefront.section-heading
                :eyebrow="$category->name"
                :title="$category->name"
                :description="$category->description"
            />
            <a href="{{ route('storefront.shop', ['category' => $category->slug]) }}" class="ys-button-primary mt-8">Open this collection</a>
        </div>
    </section>
@endsection
