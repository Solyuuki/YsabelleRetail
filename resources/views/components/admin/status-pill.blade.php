@props(['tone' => 'neutral'])

@php
    $toneClass = match ($tone) {
        'success' => 'ys-admin-pill-success',
        'warning' => 'ys-admin-pill-warning',
        'danger' => 'ys-admin-pill-danger',
        default => 'ys-admin-pill-neutral',
    };
@endphp

<span {{ $attributes->merge(['class' => "ys-admin-pill {$toneClass}"]) }}>
    {{ $slot }}
</span>
