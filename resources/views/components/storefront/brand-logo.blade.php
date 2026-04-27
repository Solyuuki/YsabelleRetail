@props([
    'variant' => 'gold-gradient',
    'alt' => 'YR | Ysabelle Retail Shop',
    'class' => '',
])

@php
    $assetPath = 'brand/yr-logo-full-transparent.png';
    $version = file_exists(public_path($assetPath)) ? filemtime(public_path($assetPath)) : null;
    $src = asset($assetPath).($version ? "?v={$version}" : '');
@endphp

<img
    src="{{ $src }}"
    alt="{{ $alt }}"
    width="2004"
    height="456"
    class="{{ trim("h-auto max-w-full object-contain {$class}") }}"
    loading="eager"
    decoding="async"
>
