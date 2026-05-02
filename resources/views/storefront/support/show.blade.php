@extends('layouts.storefront', ['title' => ($page['title'] ?? 'Support').' | Ysabelle Retail'])

@section('content')
    <section class="ys-container pb-18 pt-10 lg:pt-14">
        <x-storefront.section-heading
            :eyebrow="$page['eyebrow'] ?? 'Support'"
            :title="$page['title']"
            :description="$page['description'] ?? null"
        />

        <div class="mt-10 grid gap-6 lg:grid-cols-[0.72fr_1.28fr]">
            <aside class="space-y-5" data-reveal>
                <div class="rounded-[2rem] border border-white/8 bg-white/[0.03] p-7">
                    <p class="text-[0.76rem] font-semibold uppercase tracking-[0.32em] text-ys-gold/85">Overview</p>
                    <h2 class="mt-4 font-serif text-3xl text-ys-ivory">{{ $page['summary']['title'] ?? $page['title'] }}</h2>
                    <p class="mt-4 text-sm leading-7 text-ys-ivory/58">{{ $page['summary']['body'] ?? null }}</p>
                </div>

                @if (! empty($page['highlights']))
                    <div class="rounded-[2rem] border border-white/8 bg-ys-panel/90 p-7">
                        <p class="text-[0.76rem] font-semibold uppercase tracking-[0.32em] text-ys-gold/85">Key Details</p>
                        <ul class="mt-5 space-y-4">
                            @foreach ($page['highlights'] as $highlight)
                                <li class="flex gap-3 text-sm leading-7 text-ys-ivory/60">
                                    <span class="mt-2 h-2.5 w-2.5 shrink-0 rounded-full bg-ys-gold/90"></span>
                                    <span>{{ $highlight }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (! empty($page['actions']))
                    <div class="flex flex-wrap gap-3">
                        @foreach ($page['actions'] as $action)
                            @php
                                $actionUrl = isset($action['route'])
                                    ? route($action['route'], $action['params'] ?? [])
                                    : ($action['href'] ?? '#');
                                $actionClass = ($action['variant'] ?? 'secondary') === 'primary'
                                    ? 'ys-button-primary'
                                    : 'ys-button-secondary';
                            @endphp
                            <a href="{{ $actionUrl }}" class="{{ $actionClass }}">
                                {{ $action['label'] }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </aside>

            <div class="space-y-5">
                @foreach ($page['sections'] ?? [] as $index => $section)
                    <article
                        class="rounded-[2rem] border border-white/8 bg-white/[0.02] p-7 lg:p-8"
                        data-reveal
                        data-reveal-delay="{{ 40 + ($index * 70) }}"
                    >
                        <p class="text-[0.76rem] font-semibold uppercase tracking-[0.32em] text-ys-gold/85">Guide</p>
                        <h2 class="mt-4 font-serif text-3xl text-ys-ivory">{{ $section['title'] }}</h2>

                        <div class="mt-5 space-y-4">
                            @foreach ($section['content'] ?? [] as $paragraph)
                                <p class="text-sm leading-7 text-ys-ivory/58">{{ $paragraph }}</p>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endsection
