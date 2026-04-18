@props([
    'eyebrow' => null,
    'title',
    'description' => null,
    'align' => 'left',
])

<div {{ $attributes->class([$align === 'center' ? 'text-center' : '']) }}>
    @if ($eyebrow)
        <p class="text-xs font-semibold uppercase tracking-[0.35em] text-ys-gold/85">{{ $eyebrow }}</p>
    @endif
    <h2 class="mt-3 font-serif text-4xl leading-none text-ys-ivory md:text-5xl">{{ $title }}</h2>
    @if ($description)
        <p class="mt-4 max-w-2xl text-sm leading-7 text-ys-ivory/56 {{ $align === 'center' ? 'mx-auto' : '' }}">{{ $description }}</p>
    @endif
</div>
