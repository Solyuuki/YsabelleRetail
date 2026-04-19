@props([
    'variant' => 'gold-gradient',
    'alt' => 'Ysabelle Retail',
    'class' => '',
])

@php
    $assetPath = "brand/ysabelle-logo-{$variant}.svg";
    $version = file_exists(public_path($assetPath)) ? filemtime(public_path($assetPath)) : null;
    $src = asset($assetPath).($version ? "?v={$version}" : '');
@endphp

<img
    src="{{ $src }}"
    alt="{{ $alt }}"
    class="{{ $class }}"
    loading="eager"
    decoding="async"
>
