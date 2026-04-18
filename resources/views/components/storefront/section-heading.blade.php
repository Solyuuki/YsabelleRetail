@props([
    'eyebrow' => null,
    'title',
    'description' => null,
    'align' => 'left',
])

<div {{ $attributes->class([$align === 'center' ? 'text-center' : '']) }}>
    @if ($eyebrow)
        <p class="text-[0.82rem] font-semibold uppercase tracking-[0.34em] text-ys-gold/85">{{ $eyebrow }}</p>
    @endif
    <h2 class="mt-3.5 font-serif text-5xl leading-[0.98] text-ys-ivory md:text-[3.7rem]">{{ $title }}</h2>
    @if ($description)
        <p class="mt-5 max-w-3xl text-[1rem] leading-8 text-ys-ivory/56 {{ $align === 'center' ? 'mx-auto' : '' }}">{{ $description }}</p>
    @endif
</div>
