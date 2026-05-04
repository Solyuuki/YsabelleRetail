@php
    $guide = $page['view_data'];
    $defaultVisual = $guide['visuals']['running'];
@endphp

<div class="space-y-8 lg:space-y-10">
    <section class="ys-support-surface p-6 lg:p-8 xl:p-9" data-reveal>
        <div class="ys-support-fit-studio-grid">
            <div class="space-y-6 xl:self-start">
                <div class="ys-support-visual-panel">
                    <div class="ys-support-image-frame aspect-[1.18/0.94]">
                        <img
                            src="{{ asset($defaultVisual['image']) }}"
                            alt="{{ $defaultVisual['title'] }}"
                            class="h-full w-full object-cover"
                            data-size-guide-image
                        >
                    </div>

                    <div class="mt-5 flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="ys-support-kicker" data-size-guide-tag>{{ $defaultVisual['tag'] }}</p>
                            <h2 class="mt-3 font-serif text-3xl text-ys-ivory" data-size-guide-title>{{ $defaultVisual['title'] }}</h2>
                            <p class="mt-3 max-w-xl text-sm leading-7 text-ys-ivory/58" data-size-guide-caption>{{ $defaultVisual['copy'] }}</p>
                        </div>
                        <span class="ys-support-micro-pill">PH / EU fit guide</span>
                    </div>
                </div>

                <div class="ys-support-recommendation-card">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-ys-gold/78">Recommendation</p>
                            <h3 class="mt-3 text-2xl font-semibold text-ys-ivory" data-fit-headline>True to size</h3>
                            <p class="mt-2 max-w-3xl text-sm leading-7 text-ys-ivory/64" data-fit-recommendation>
                                Start with your regular size for a secure performance fit.
                            </p>
                        </div>
                        <div class="ys-support-size-badge">
                            <p class="text-[0.72rem] font-semibold uppercase tracking-[0.2em] text-ys-ivory/44">Suggested starting size</p>
                            <p class="mt-2 text-3xl font-semibold text-ys-ivory" data-fit-size>Size 8</p>
                        </div>
                    </div>

                    <div class="mt-5 rounded-[1.15rem] border border-white/8 bg-black/25 p-4 lg:p-5">
                        <p class="text-[0.72rem] font-semibold uppercase tracking-[0.2em] text-ys-ivory/44">Fit note</p>
                        <p class="mt-2 max-w-3xl text-sm leading-7 text-ys-ivory/68" data-fit-confidence>
                            Best for shoppers who like a balanced, close-to-foot feel.
                        </p>
                    </div>
                </div>
            </div>

            <div class="ys-support-fit-studio-content space-y-6" data-size-guide>
                <div class="ys-support-panel">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="ys-support-kicker">Find your starting size</p>
                            <h2 class="mt-3 font-serif text-3xl text-ys-ivory">Choose your usual shoe size</h2>
                        </div>
                        <span class="ys-support-micro-pill">PH size guide</span>
                    </div>

                    <fieldset class="mt-5">
                        <legend class="sr-only">Usual shoe size</legend>
                        <div class="flex flex-wrap gap-2.5">
                            @foreach ($guide['sizes'] as $size)
                                <button
                                    type="button"
                                    class="ys-support-choice {{ $size === '8' ? 'is-active' : '' }}"
                                    data-size-option
                                    data-size-value="{{ $size }}"
                                    aria-pressed="{{ $size === '8' ? 'true' : 'false' }}"
                                >
                                    {{ $size }}
                                </button>
                            @endforeach
                        </div>
                    </fieldset>
                </div>

                <div class="ys-support-fit-controls-grid">
                    <div class="ys-support-panel">
                        <p class="ys-support-kicker">Use case</p>
                        <div class="mt-4 grid gap-3">
                            @foreach ($guide['use_cases'] as $useCase)
                                <button
                                    type="button"
                                    class="ys-support-card-button {{ $useCase['id'] === 'running' ? 'is-active' : '' }}"
                                    data-use-case-option
                                    data-use-case-value="{{ $useCase['id'] }}"
                                    aria-pressed="{{ $useCase['id'] === 'running' ? 'true' : 'false' }}"
                                >
                                    <span class="block text-left text-sm font-semibold text-ys-ivory">{{ $useCase['label'] }}</span>
                                    <span class="mt-1 block text-left text-xs leading-5 text-ys-ivory/46">{{ $useCase['detail'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="ys-support-panel">
                        <p class="ys-support-kicker">Local fit guidance</p>
                        <div class="mt-4 grid gap-2.5">
                            @foreach ($guide['foot_types'] as $footType)
                                <button
                                    type="button"
                                    class="ys-support-choice is-wide {{ $footType['id'] === 'regular' ? 'is-active' : '' }}"
                                    data-foot-type-option
                                    data-foot-type-value="{{ $footType['id'] }}"
                                    aria-pressed="{{ $footType['id'] === 'regular' ? 'true' : 'false' }}"
                                >
                                    {{ $footType['label'] }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="grid gap-8 xl:grid-cols-[1.05fr_0.95fr] xl:gap-10">
        <div class="ys-support-surface p-6 lg:p-8 xl:p-9" data-reveal data-reveal-delay="80">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="ys-support-kicker">Sample fit notes</p>
                    <h2 class="mt-3 font-serif text-3xl text-ys-ivory">Three grounded fit references</h2>
                </div>
                <span class="ys-support-micro-pill">No guesswork</span>
            </div>

            <div class="mt-6 grid gap-5 md:grid-cols-3">
                @foreach ($guide['sample_shoes'] as $sample)
                    <article class="ys-support-product-card" data-sample-shoe data-sample-use-case="{{ $sample['use_case'] }}">
                        <div class="ys-support-image-frame aspect-[1/0.88]">
                            <img src="{{ asset($sample['image']) }}" alt="{{ $sample['title'] }}" class="h-full w-full object-cover">
                        </div>
                        <div class="p-4">
                            <h3 class="text-lg font-semibold text-ys-ivory">{{ $sample['title'] }}</h3>
                            <p class="mt-2 text-sm leading-6 text-ys-ivory/60">{{ $sample['size_note'] }}</p>
                            <p class="mt-3 text-xs uppercase tracking-[0.2em] text-ys-gold/76">{{ $sample['fit_note'] }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>

        <aside class="ys-support-surface p-6 lg:p-8 xl:p-9" data-reveal data-reveal-delay="140">
            <p class="ys-support-kicker">Before you choose</p>
            <h2 class="mt-3 font-serif text-3xl text-ys-ivory">Keep the guidance realistic</h2>
            <ul class="mt-5 space-y-4">
                @foreach ($guide['tips'] as $tip)
                    <li class="flex gap-3 text-sm leading-7 text-ys-ivory/60">
                        <span class="mt-2 h-2 w-2 rounded-full bg-ys-gold/90"></span>
                        <span>{{ $tip }}</span>
                    </li>
                @endforeach
            </ul>
        </aside>
    </section>
</div>
