@php
    $returns = $page['view_data'];
    $defaultAction = $returns['actions']['return-item'];
@endphp

<div class="space-y-8 lg:space-y-10" data-returns-assistant>
    <section class="ys-support-surface p-6 lg:p-8 xl:p-9" data-reveal>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="ys-support-kicker">14-day return flow</p>
                <h2 class="mt-3 font-serif text-3xl text-ys-ivory">A clear path from request to resolution</h2>
            </div>
            <span class="ys-support-micro-pill">Within 14 days</span>
        </div>

        <div class="mt-6 grid gap-5 md:grid-cols-4">
            @foreach ($returns['flow'] as $step)
                <article class="ys-support-step-card">
                    <p class="ys-support-info-label">{{ $step['label'] }}</p>
                    <p class="mt-3 text-sm leading-7 text-ys-ivory/60">{{ $step['detail'] }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="grid gap-8 xl:grid-cols-[0.88fr_1.12fr] xl:gap-10">
        <aside class="ys-support-surface p-6 lg:p-8 xl:p-9" data-reveal data-reveal-delay="80">
            <p class="ys-support-kicker">Choose your path</p>
            <h2 class="mt-3 font-serif text-3xl text-ys-ivory">Start with the action that fits your case</h2>

            <div class="mt-6 grid gap-3">
                @foreach ($returns['actions'] as $actionKey => $action)
                    <button
                        type="button"
                        class="ys-support-card-button {{ $actionKey === 'return-item' ? 'is-active' : '' }}"
                        data-returns-action
                        data-action-id="{{ $actionKey }}"
                        data-action-title="{{ $action['title'] }}"
                        data-action-summary="{{ $action['summary'] }}"
                        data-action-mailto="{{ $action['mailto'] }}"
                        aria-pressed="{{ $actionKey === 'return-item' ? 'true' : 'false' }}"
                    >
                        <span class="block text-left text-sm font-semibold text-ys-ivory">{{ $action['title'] }}</span>
                        <span class="mt-1 block text-left text-xs leading-5 text-ys-ivory/44">{{ $action['summary'] }}</span>
                    </button>
                @endforeach
            </div>

            <div class="mt-7 rounded-[1.4rem] border border-white/8 bg-black/20 p-5">
                <p class="ys-support-info-label">Conditions</p>
                <ul class="mt-4 space-y-3">
                    @foreach ($returns['conditions'] as $condition)
                        <li class="flex gap-3 text-sm leading-7 text-ys-ivory/58">
                            <span class="mt-2 h-2 w-2 rounded-full bg-ys-gold/90"></span>
                            <span>{{ $condition }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </aside>

        <div class="ys-support-surface p-6 lg:p-8 xl:p-9" data-reveal data-reveal-delay="130">
            <p class="ys-support-kicker">Action details</p>
            <h2 class="mt-3 font-serif text-3xl text-ys-ivory" data-returns-title>{{ $defaultAction['title'] }}</h2>
            <p class="mt-4 max-w-3xl text-sm leading-7 text-ys-ivory/60" data-returns-summary>{{ $defaultAction['summary'] }}</p>

            @foreach ($returns['actions'] as $actionKey => $action)
                <section data-returns-panel="{{ $actionKey }}" @class(['mt-6', 'hidden' => $actionKey !== 'return-item'])>
                    @if (! empty($action['process_steps']))
                        <div class="ys-support-process-grid">
                            @foreach ($action['process_steps'] as $step)
                                <article class="ys-support-process-step">
                                    <span class="ys-support-process-node">{{ $loop->iteration }}</span>
                                    <h3 class="mt-4 text-lg font-semibold text-ys-ivory">{{ $step['title'] }}</h3>
                                    <p class="mt-2 text-sm leading-7 text-ys-ivory/60">{{ $step['detail'] }}</p>
                                </article>
                            @endforeach
                        </div>
                    @endif

                    <div class="rounded-[1.45rem] border border-white/8 bg-white/[0.02] p-5">
                        <p class="ys-support-info-label">What support needs from you</p>
                        <ul class="mt-4 space-y-3">
                            @foreach ($action['details'] as $detail)
                                <li class="flex gap-3 text-sm leading-7 text-ys-ivory/60">
                                    <span class="mt-2 h-2 w-2 rounded-full bg-ys-gold/90"></span>
                                    <span>{{ $detail }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </section>
            @endforeach
        </div>
    </section>
</div>
