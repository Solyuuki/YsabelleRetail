@props([
    'trustMarks' => config('storefront.trust_marks', []),
])

@php
    $marks = collect($trustMarks)->take(4)->values();
    $marqueeSequenceRepeats = 4;

    $iconFor = static function (string $label): string {
        return match ($label) {
            'Free Shipping' => 'shipping',
            '14-Day Returns' => 'returns',
            'Authenticity' => 'authenticity',
            default => 'premium',
        };
    };
@endphp

<section class="ys-trust-strip" aria-label="Store trust highlights">
    <div class="ys-trust-marquee-viewport">
        <div class="ys-trust-marquee-track">
            @for ($copy = 0; $copy < 2; $copy++)
                <div class="ys-trust-marquee-group" @if ($copy === 1) aria-hidden="true" @endif>
                    @for ($sequence = 0; $sequence < $marqueeSequenceRepeats; $sequence++)
                        @foreach ($marks as $mark)
                            @php
                                $icon = $iconFor($mark['label']);
                            @endphp

                            <article class="ys-trust-item">
                                <span class="ys-trust-item-glint" aria-hidden="true"></span>
                                <span class="ys-trust-item-sparkle" aria-hidden="true"></span>

                                <span class="ys-trust-item-icon" aria-hidden="true">
                                    @if ($icon === 'shipping')
                                        <svg class="ys-trust-item-icon-mark h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                            <path d="M3.5 7.5h11v7.5h-11Z" />
                                            <path d="M14.5 10h3.2l2.3 2.7V15h-5.5Z" stroke-linejoin="round" />
                                            <circle cx="7.5" cy="17" r="1.7" />
                                            <circle cx="17.5" cy="17" r="1.7" />
                                        </svg>
                                    @elseif ($icon === 'returns')
                                        <svg class="ys-trust-item-icon-mark h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                            <path d="M7 7H3v4" stroke-linecap="round" stroke-linejoin="round" />
                                            <path d="M3.5 11A8.5 8.5 0 1 0 7 5.2" stroke-linecap="round" />
                                        </svg>
                                    @elseif ($icon === 'authenticity')
                                        <svg class="ys-trust-item-icon-mark h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                            <path d="M12 3.5 4.5 7.2v5.6c0 4.2 3 8.1 7.5 8.7 4.5-.6 7.5-4.5 7.5-8.7V7.2L12 3.5Z" />
                                        </svg>
                                    @else
                                        <svg class="ys-trust-item-icon-mark h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                            <path d="m12 3 1.3 3.7L17 8l-3.7 1.3L12 13l-1.3-3.7L7 8l3.7-1.3L12 3Z" stroke-linejoin="round" />
                                            <path d="m18.5 12 0.8 2.2 2.2 0.8-2.2 0.8-0.8 2.2-0.8-2.2-2.2-0.8 2.2-0.8 0.8-2.2Z" stroke-linejoin="round" />
                                        </svg>
                                    @endif
                                </span>

                                <div class="ys-trust-item-copy">
                                    <p class="ys-trust-item-title">{{ $mark['label'] }}</p>
                                    <p class="ys-trust-item-description">{{ $mark['description'] }}</p>
                                </div>
                            </article>

                            <span class="ys-trust-item-gap" aria-hidden="true"></span>
                        @endforeach
                    @endfor
                </div>
            @endfor
        </div>
    </div>
</section>
