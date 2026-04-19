@props([
    'imageUrl' => null,
    'title',
    'eyebrow' => null,
    'alt' => null,
    'loading' => 'lazy',
    'fetchpriority' => 'auto',
    'imageClass' => '',
    'fallbackClass' => '',
])

@php
    $hasImage = filled($imageUrl);
    $resolvedAlt = filled($alt) ? $alt : "{$title} by Ysabelle Retail";
@endphp

<div
    {{ $attributes->class([
        'ys-product-media-shell',
        'is-fallback-visible' => ! $hasImage,
    ])->merge([
        'data-product-media' => true,
    ]) }}
>
    @if ($hasImage)
        <img
            src="{{ $imageUrl }}"
            alt="{{ $resolvedAlt }}"
            loading="{{ $loading }}"
            decoding="async"
            @if ($fetchpriority !== 'auto') fetchpriority="{{ $fetchpriority }}" @endif
            class="ys-product-media-image {{ $imageClass }}"
            data-product-media-image
        >
    @endif

    <div class="ys-product-media-fallback {{ $fallbackClass }}" data-product-media-fallback>
        <span class="ys-product-media-badge">{{ $eyebrow ?: 'Ysabelle Retail' }}</span>
        <div class="mt-auto">
            <p class="ys-product-media-title">{{ $title }}</p>
            <p class="ys-product-media-caption">Curated catalog imagery</p>
        </div>
    </div>
</div>
