@props([
    'footerLinks' => config('storefront.footer', []),
])

<footer class="border-t border-white/6 bg-ys-panel/60">
    <div class="ys-container py-16">
        <div class="grid gap-12 lg:grid-cols-[1.1fr_0.45fr_0.45fr]">
            <div class="max-w-md">
                <p class="font-serif text-3xl text-ys-gold">Ysabelle</p>
                <p class="mt-2 text-xs uppercase tracking-[0.35em] text-ys-ivory/40">Retail Store &middot; Step Into Style</p>
                <p class="mt-6 text-sm leading-7 text-ys-ivory/58">
                    Premium footwear crafted for those who move with intention. Every pair, a statement of refined performance.
                </p>

                <div class="mt-8 flex items-center gap-3 text-ys-ivory/55">
                    @foreach (['instagram', 'facebook', 'twitter'] as $channel)
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/10 text-xs uppercase tracking-[0.2em]">
                            {{ strtoupper(substr($channel, 0, 1)) }}
                        </span>
                    @endforeach
                </div>
            </div>

            <div>
                <h2 class="text-xs font-semibold uppercase tracking-[0.35em] text-ys-gold">Shop</h2>
                <ul class="mt-6 space-y-3 text-sm text-ys-ivory/58">
                    @foreach ($footerLinks['shop'] ?? [] as $link)
                        <li>
                            <a href="{{ route($link['route'], $link['params'] ?? []) }}" class="transition hover:text-ys-gold">{{ $link['label'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div>
                <h2 class="text-xs font-semibold uppercase tracking-[0.35em] text-ys-gold">Support</h2>
                <ul class="mt-6 space-y-3 text-sm text-ys-ivory/58">
                    @foreach ($footerLinks['support'] ?? [] as $link)
                        <li>
                            <a href="{{ $link['href'] }}" class="transition hover:text-ys-gold">{{ $link['label'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="mt-14 flex flex-col gap-3 border-t border-white/6 pt-6 text-xs text-ys-ivory/35 sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; {{ now()->year }} Ysabelle Retail Store. All rights reserved.</p>
            <p>Crafted with intention.</p>
        </div>
    </div>
</footer>
