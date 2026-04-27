<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @php
            $versionedPublicAsset = static function (string $path): string {
                $absolutePath = public_path($path);

                if (! file_exists($absolutePath)) {
                    return asset($path);
                }

                return asset($path).'?v='.filemtime($absolutePath);
            };
        @endphp
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'Ysabelle Store' }}</title>
        @include('partials.icon-head')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-stone-950 text-stone-100">
        @include('partials.role-shortcuts-config')
        <div class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top,_rgba(245,158,11,0.12),_transparent_30%),radial-gradient(circle_at_bottom_right,_rgba(217,119,6,0.14),_transparent_25%)]"></div>

        <header class="border-b border-white/10 bg-stone-950/90 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-5.5">
                <div>
                    <x-storefront.brand-logo class="block w-[10.5rem] md:w-[12rem]" />
                    <a href="{{ route('storefront.home') }}" class="text-lg font-semibold text-white">Retail Platform Foundation</a>
                </div>

                <nav class="flex items-center gap-3 text-sm text-stone-300">
                    <a href="{{ route('storefront.home') }}" class="rounded-full px-4 py-2 transition hover:bg-white/5 hover:text-white">Storefront</a>
                    <a href="{{ route('storefront.catalog.products.index') }}" class="rounded-full px-4 py-2 transition hover:bg-white/5 hover:text-white">Catalog</a>
                    <a href="{{ route('storefront.cart.index') }}" class="rounded-full px-4 py-2 transition hover:bg-white/5 hover:text-white">Cart</a>
                    @guest
                        <a href="{{ route('login') }}" class="rounded-full border border-white/10 px-4 py-2 transition hover:border-amber-300/50 hover:text-white">Auth</a>
                    @elseif (auth()->user()?->isAdmin())
                        <a href="{{ route('admin.dashboard') }}" class="rounded-full border border-amber-300/30 px-4 py-2 text-amber-200 transition hover:border-amber-200 hover:text-white">Admin</a>
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="rounded-full border border-white/10 px-4 py-2 transition hover:border-amber-300/50 hover:text-white">Sign out</button>
                        </form>
                    @endguest
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-6 py-10">
            @yield('content')
        </main>
    </body>
</html>
