@extends('layouts.storefront', ['title' => ($page['title'] ?? 'Support').' | Ysabelle Retail'])

@section('content')
    <section class="ys-container pb-24 pt-14 lg:pb-30 lg:pt-18" data-support-page="{{ $page['key'] }}">
        <div class="ys-support-hero" data-reveal>
            <div class="grid gap-8 xl:grid-cols-[1.16fr_0.84fr] xl:gap-10">
                <div>
                    <x-storefront.section-heading
                        :eyebrow="$page['eyebrow'] ?? 'Support'"
                        :title="$page['title']"
                        :description="$page['description'] ?? null"
                    />

                    <p class="mt-6 max-w-3xl text-sm leading-7 text-ys-ivory/60">
                        {{ $page['summary'] ?? null }}
                    </p>

                    @if (! empty($page['hero_actions']))
                        <div class="mt-8 flex flex-wrap gap-3">
                            @foreach ($page['hero_actions'] as $action)
                                <a href="{{ $action['url'] }}" class="{{ $action['variant'] === 'primary' ? 'ys-button-primary' : 'ys-button-secondary' }}">
                                    {{ $action['label'] }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

                <aside class="ys-support-surface p-6 lg:p-8 xl:p-9">
                    <p class="ys-support-kicker">Support Hub</p>
                    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                        <div class="ys-support-info-card">
                            <p class="ys-support-info-label">Email</p>
                            <a href="{{ $supportContact['general_mailto'] }}" class="ys-support-inline-link">
                                {{ $supportContact['email'] }}
                            </a>
                        </div>
                        <div class="ys-support-info-card">
                            <p class="ys-support-info-label">Phone</p>
                            <a href="{{ $supportContact['phone_href'] }}" class="ys-support-inline-link">
                                {{ $supportContact['phone_display'] }}
                            </a>
                        </div>
                        <div class="ys-support-info-card sm:col-span-2 xl:col-span-1">
                            <p class="ys-support-info-label">Support Hub</p>
                            <p class="text-sm leading-6 text-ys-ivory/68">{{ $supportContact['address'] }}</p>
                            <p class="mt-2 text-xs uppercase tracking-[0.22em] text-ys-ivory/36">{{ $supportContact['hours'] }}</p>
                        </div>
                    </div>
                </aside>
            </div>
        </div>

        <div class="mt-10 lg:mt-12">
            @include("storefront.support.partials.{$page['view']}", ['page' => $page, 'supportContact' => $supportContact])
        </div>
    </section>
@endsection
