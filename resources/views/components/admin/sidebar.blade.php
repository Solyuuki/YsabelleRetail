@php
    $currentRoute = request()->route()?->getName();
    $currentGroup = null;
@endphp

<aside class="ys-admin-sidebar" data-admin-sidebar>
    <a href="{{ route('admin.dashboard') }}" class="ys-admin-sidebar-brand">
        <x-storefront.brand-logo class="ys-admin-sidebar-brand-logo" />
        <p class="ys-admin-sidebar-brand-label">Admin Back Office</p>
    </a>

    <nav class="ys-admin-sidebar-nav">
        @foreach (config('admin.navigation', []) as $item)
            @if (($item['group'] ?? null) !== $currentGroup)
                @php
                    $currentGroup = $item['group'] ?? null;
                @endphp
                <p class="ys-admin-nav-group">{{ $currentGroup }}</p>
            @endif

            <a
                href="{{ route($item['route']) }}"
                class="ys-admin-nav-link {{ str_starts_with((string) $currentRoute, str_replace('.index', '', $item['route'])) || $currentRoute === $item['route'] ? 'is-active' : '' }}"
            >
                <x-admin.icon :name="$item['icon']" class="h-5 w-5" />
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <div class="rounded-[1.1rem] border border-white/7 bg-white/[0.03] p-4 text-sm text-ys-ivory/48">
        <p class="font-semibold text-ys-ivory">Shared Inventory</p>
        <p class="mt-2 leading-6">Orders, POS, stock updates, and imports all write to one audit trail.</p>
    </div>
</aside>
