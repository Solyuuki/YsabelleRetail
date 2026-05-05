<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'Admin | Ysabelle Retail' }}</title>
        @include('partials.icon-head')
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=cormorant-garamond:500,600,700|instrument-sans:400,500,600,700" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body
        class="ys-admin-shell"
        data-admin-app
        data-admin-activity-endpoint="{{ route('admin.realtime.feed') }}"
        data-protected-page="{{ request()->attributes->get('prevent_back_history') ? 'true' : 'false' }}"
    >
        <div class="ys-admin-overlay" data-admin-overlay></div>

        <div class="ys-admin-grid">
            <x-admin.sidebar />

            <main class="ys-admin-main">
                <x-admin.topbar />

                <div class="ys-admin-page">
                    @yield('content')
                </div>
            </main>
        </div>

        <x-storefront.toast-stack />
    </body>
</html>
