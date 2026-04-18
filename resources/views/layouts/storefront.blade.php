<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'Ysabelle Retail' }}</title>
        <meta name="description" content="{{ $description ?? 'Premium footwear crafted for movement, legacy, and refined performance.' }}">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=cormorant-garamond:500,600,700|instrument-sans:400,500,600,700" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-ys-ink text-ys-ivory selection:bg-ys-gold/20 selection:text-ys-ivory">
        <div class="pointer-events-none fixed inset-0 -z-10 bg-[radial-gradient(circle_at_top_right,_rgba(193,145,52,0.12),_transparent_26%),radial-gradient(circle_at_top_left,_rgba(115,85,20,0.1),_transparent_22%),linear-gradient(180deg,_rgba(14,14,15,1),_rgba(8,8,9,1))]"></div>

        <x-storefront.header
            :navigation="($storefrontNavigation ?? config('storefront.navigation', []))"
            :cart-count="($storefrontCartCount ?? 0)"
        />

        <main class="min-h-[calc(100vh-5rem)] pt-20">
            @yield('content')
        </main>

        <x-storefront.footer :footer-links="($storefrontFooter ?? config('storefront.footer', []))" />
        <x-storefront.toast-stack />
        <x-storefront.chat-widget />
    </body>
</html>
