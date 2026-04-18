@props([
    'navigation' => config('storefront.navigation', []),
    'cartCount' => 0,
])

@php
    $currentRoute = request()->route()?->getName();
@endphp

<header class="fixed inset-x-0 top-0 z-50 border-b border-white/5 bg-ys-ink/85 backdrop-blur-xl">
    <div class="ys-container flex h-20 items-center justify-between gap-6">
        <a href="{{ route('storefront.home') }}" class="flex shrink-0 items-center gap-2 text-sm tracking-[0.35em] text-ys-ivory/80 transition hover:text-ys-gold">
            <span class="font-serif text-2xl tracking-normal text-ys-gold">Ysabelle</span>
            <span class="hidden text-[0.62rem] uppercase sm:block">Retail</span>
        </a>

        <button
            type="button"
            class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-white/10 text-ys-ivory transition hover:border-ys-gold/40 hover:text-ys-gold lg:hidden"
            data-mobile-nav-toggle
            aria-label="Open navigation"
        >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round" />
            </svg>
        </button>

        <nav class="hidden items-center gap-8 text-sm font-medium text-ys-ivory/60 lg:flex">
            @foreach ($navigation as $link)
                @php
                    $params = $link['params'] ?? [];
                    $isActive = $currentRoute === $link['route']
                        && collect($params)->every(fn ($value, $key) => request($key) === $value);
                @endphp
                <a
                    href="{{ route($link['route'], $params) }}"
                    class="transition hover:text-ys-gold {{ $isActive ? 'text-ys-gold' : '' }}"
                >
                    {{ $link['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="hidden items-center gap-4 lg:flex">
            <a href="{{ route('storefront.shop') }}" class="ys-icon-button" aria-label="Search catalog">
                <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                    <circle cx="11" cy="11" r="6.5" />
                    <path d="m16 16 4.5 4.5" stroke-linecap="round" />
                </svg>
            </a>

            @auth
                <div class="relative" data-account-menu>
                    <button type="button" class="ys-icon-button" data-account-menu-trigger aria-label="Account menu">
                        <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                            <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" />
                            <path d="M4.5 21a7.5 7.5 0 0 1 15 0" stroke-linecap="round" />
                        </svg>
                    </button>

                    <div class="ys-dropdown hidden w-44" data-account-menu-panel>
                        <p class="border-b border-white/6 px-4 py-3 text-xs text-ys-ivory/60">{{ auth()->user()->email }}</p>
                        <a href="{{ route('storefront.account.index') }}" class="ys-dropdown-link bg-ys-gold/95 text-ys-ink">My Orders</a>
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="ys-dropdown-link w-full text-left">
                                Sign out
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <a href="{{ route('login') }}" class="text-sm font-semibold text-ys-ivory transition hover:text-ys-gold">Sign in</a>
            @endauth

            <a href="{{ route('storefront.cart.index') }}" class="ys-icon-button relative" aria-label="Shopping bag">
                <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                    <path d="M7 8V6a5 5 0 0 1 10 0v2" stroke-linecap="round" />
                    <path d="M5.5 8.5h13l-.9 10.5H6.4L5.5 8.5Z" />
                </svg>
                @if ($cartCount > 0)
                    <span class="absolute -right-1.5 -top-1.5 inline-flex min-h-5 min-w-5 items-center justify-center rounded-full bg-ys-gold px-1 text-[10px] font-semibold text-ys-ink">
                        {{ $cartCount }}
                    </span>
                @endif
            </a>
        </div>
    </div>

    <div class="hidden border-t border-white/6 bg-ys-panel lg:hidden" data-mobile-nav-panel>
        <div class="ys-container space-y-3 py-5">
            @foreach ($navigation as $link)
                <a href="{{ route($link['route'], $link['params'] ?? []) }}" class="block rounded-2xl px-4 py-3 text-sm font-medium text-ys-ivory/80 transition hover:bg-white/5 hover:text-ys-gold">
                    {{ $link['label'] }}
                </a>
            @endforeach

            <div class="flex items-center gap-3 pt-2">
                <a href="{{ route('storefront.cart.index') }}" class="ys-button-secondary text-sm">Shopping bag ({{ $cartCount }})</a>
                @auth
                    <a href="{{ route('storefront.account.index') }}" class="ys-button-secondary text-sm">My account</a>
                @else
                    <a href="{{ route('login') }}" class="ys-button-primary text-sm">Sign in</a>
                @endauth
            </div>
        </div>
    </div>
</header>
