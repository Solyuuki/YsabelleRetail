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
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? 'Authentication | Ysabelle Retail' }}</title>
        <meta
            name="description"
            content="{{ $description ?? 'Secure Ysabelle Retail sign in and account creation.' }}"
        >
        <link rel="preconnect" href="https://fonts.bunny.net">
        @include('partials.icon-head')
        <link
            href="https://fonts.bunny.net/css?family=cormorant-garamond:500,600,700|instrument-sans:400,500,600,700"
            rel="stylesheet"
        />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#040404] text-ys-ivory selection:bg-ys-gold/20 selection:text-ys-ivory">
        @include('partials.role-shortcuts-config')
        <div class="fixed inset-0 -z-10 bg-[radial-gradient(circle_at_top,_rgba(212,166,59,0.17),_transparent_24%),radial-gradient(circle_at_bottom_left,_rgba(122,84,23,0.12),_transparent_28%),linear-gradient(180deg,_#070707_0%,_#040404_48%,_#020202_100%)]"></div>
        <div class="fixed inset-0 -z-10 opacity-60 [background-image:linear-gradient(rgba(255,255,255,0.025)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.025)_1px,transparent_1px)] [background-size:4.75rem_4.75rem] [mask-image:radial-gradient(circle_at_center,black,transparent_78%)]"></div>

        <main class="flex min-h-screen items-center justify-center px-4 py-8 sm:px-6 lg:px-8">
            @yield('content')
        </main>

        <x-storefront.toast-stack />
    </body>
</html>
