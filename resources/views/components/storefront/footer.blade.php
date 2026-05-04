@props([
    'footerLinks' => config('storefront.footer', []),
])

<footer class="border-t border-white/6 bg-ys-panel/60">
    <div class="ys-container py-18 lg:py-20">
        <div class="grid gap-14 lg:grid-cols-[1.1fr_0.45fr_0.45fr]">
            <div class="max-w-lg">
                <x-storefront.brand-logo class="block w-[10rem] sm:w-[10.75rem] lg:w-[11.5rem]" />
                <p class="mt-3 text-[0.78rem] uppercase tracking-[0.34em] text-ys-ivory/40">Retail Store &middot; Step Into Style</p>
                <p class="mt-6 text-[1rem] leading-8 text-ys-ivory/58">
                    Premium footwear crafted for those who move with intention. Every pair, a statement of refined performance.
                </p>

                <div class="mt-9 flex items-center gap-3.5 text-ys-ivory/55">
                    @php
                        $socialLinks = [
                            'instagram' => 'https://www.instagram.com/ysabelleretail?igsh=cGVxNnd6bGVsY3A5',
                            'facebook' => 'https://www.facebook.com/profile.php?id=61588817297784',
                            'tiktok' => 'https://www.tiktok.com/@ysabelleretail',
                        ];
                    @endphp
                    @foreach ($socialLinks as $channel => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/10 text-ys-ivory/55 transition hover:border-ys-gold hover:text-ys-gold" aria-label="Visit our {{ $channel }} page">
                            @switch($channel)
                                @case('instagram')
                                    <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <rect x="4.5" y="4.5" width="15" height="15" rx="4.2" />
                                        <circle cx="12" cy="12" r="3.3" />
                                        <circle cx="16.8" cy="7.2" r="0.8" fill="currentColor" stroke="none" />
                                    </svg>
                                    @break
                                @case('facebook')
                                    <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                        <path d="M14.2 8.1h2.2V4.5a28 28 0 0 0-3.2-.2c-3.2 0-5.4 1.9-5.4 5.5v3.1H4.5V17h3.3v6.7h4.1V17h3.4l.5-4.1h-3.9v-2.7c0-1.2.3-2.1 2.3-2.1Z" />
                                    </svg>
                                    @break
                                @case('tiktok')
                                    <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                        <path d="M15.3 3.5c.4 2.7 1.9 4.4 4.4 4.6v3.7a7.6 7.6 0 0 1-4.3-1.3v6.1c0 4-2.4 6.5-6.1 6.5-3.3 0-5.8-2.3-5.8-5.4 0-3.5 2.7-5.9 6.6-5.6v3.8c-1.6-.3-2.8.5-2.8 1.8 0 1.1.9 1.8 2 1.8 1.3 0 2.1-.8 2.1-2.6V3.5h3.9Z" />
                                    </svg>
                                    @break
                            @endswitch
                        </a>
                    @endforeach
                </div>
            </div>

            <div>
                <h2 class="text-[0.78rem] font-semibold uppercase tracking-[0.35em] text-ys-gold">Shop</h2>
                <ul class="mt-6 space-y-3.5 text-[0.98rem] text-ys-ivory/58">
                    @foreach ($footerLinks['shop'] ?? [] as $link)
                        <li>
                            <a href="{{ route($link['route'], $link['params'] ?? []) }}" class="transition hover:text-ys-gold">{{ $link['label'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div>
                <h2 class="text-[0.78rem] font-semibold uppercase tracking-[0.35em] text-ys-gold">Support</h2>
                <ul class="mt-6 space-y-3.5 text-[0.98rem] text-ys-ivory/58">
                    @foreach ($footerLinks['support'] ?? [] as $link)
                        @php
                            $supportUrl = isset($link['route'])
                                ? route($link['route'], $link['params'] ?? [])
                                : ($link['href'] ?? '#');
                        @endphp
                        <li>
                            <a href="{{ $supportUrl }}" class="transition hover:text-ys-gold">{{ $link['label'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="mt-16 flex flex-col gap-3 border-t border-white/6 pt-7 text-[0.82rem] text-ys-ivory/35 sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; {{ now()->year }} Ysabelle Retail Store. All rights reserved.</p>
            <p>Crafted with intention.</p>
        </div>
    </div>
</footer>
