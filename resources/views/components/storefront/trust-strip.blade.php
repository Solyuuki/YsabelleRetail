@props([
    'trustMarks' => config('storefront.trust_marks', []),
])

<div class="border-y border-white/6 bg-white/[0.02]">
    <div class="ys-container grid gap-4 py-6 md:grid-cols-2 xl:grid-cols-4">
        @foreach ($trustMarks as $mark)
            <div class="flex items-center gap-4 rounded-2xl border border-white/[0.04] bg-white/[0.01] px-4 py-4">
                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-ys-gold/20 bg-ys-gold/10 text-ys-gold">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path d="M12 3.5 4.5 7.2v5.6c0 4.2 3 8.1 7.5 8.7 4.5-.6 7.5-4.5 7.5-8.7V7.2L12 3.5Z" />
                    </svg>
                </span>
                <div>
                    <p class="text-sm font-semibold text-ys-ivory">{{ $mark['label'] }}</p>
                    <p class="text-xs text-ys-ivory/48">{{ $mark['description'] }}</p>
                </div>
            </div>
        @endforeach
    </div>
</div>
