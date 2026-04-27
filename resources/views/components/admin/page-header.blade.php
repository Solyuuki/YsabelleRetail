@props([
    'eyebrow' => 'Admin',
    'title',
    'description' => null,
])

<div class="ys-admin-page-header" data-admin-panel>
    <div>
        <p class="text-[0.74rem] font-semibold uppercase tracking-[0.34em] text-ys-gold/76">{{ $eyebrow }}</p>
        <h1 class="ys-admin-page-title">{{ $title }}</h1>
        @if ($description)
            <p class="ys-admin-page-copy">{{ $description }}</p>
        @endif
    </div>

    @if (trim((string) $slot) !== '')
        <div class="ys-admin-inline-actions">
            {{ $slot }}
        </div>
    @endif
</div>
