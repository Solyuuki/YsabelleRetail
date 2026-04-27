<header class="ys-admin-topbar">
    <div class="flex items-center gap-3">
        <button type="button" class="ys-admin-button-secondary lg:hidden" data-admin-sidebar-toggle>
            <span class="sr-only">Toggle sidebar</span>
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round" />
            </svg>
        </button>
        <div>
            <p class="text-[0.72rem] uppercase tracking-[0.28em] text-ys-gold/72">Control Center</p>
            <p class="text-sm text-ys-ivory/54">{{ now()->format('l, F j, Y') }}</p>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <a href="{{ route('storefront.home') }}" class="ys-admin-button-secondary">Open storefront</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="ys-admin-button-secondary">Sign out</button>
        </form>
        <div class="rounded-[1rem] border border-white/7 bg-white/[0.03] px-4 py-3 text-right">
            <p class="text-xs uppercase tracking-[0.24em] text-ys-ivory/38">Signed in</p>
            <p class="mt-1 text-sm font-semibold text-ys-ivory">{{ auth()->user()?->email }}</p>
        </div>
    </div>
</header>
