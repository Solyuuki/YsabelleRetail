@php
    $versionedPublicAsset = static function (string $path): string {
        $absolutePath = public_path($path);

        if (! file_exists($absolutePath)) {
            return asset($path);
        }

        return asset($path).'?v='.filemtime($absolutePath);
    };
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'Service status | Ysabelle Retail' }}</title>
        @include('partials.icon-head')
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=cormorant-garamond:500,600,700|instrument-sans:400,500,600,700" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#050505] text-ys-ivory selection:bg-ys-gold/20 selection:text-ys-ivory">
        <div class="fixed inset-0 -z-10 bg-[radial-gradient(circle_at_top,_rgba(212,166,59,0.18),_transparent_24%),radial-gradient(circle_at_bottom_right,_rgba(122,84,23,0.16),_transparent_28%),linear-gradient(180deg,_#090909_0%,_#040404_52%,_#020202_100%)]"></div>
        <div class="fixed inset-0 -z-10 opacity-60 [background-image:linear-gradient(rgba(255,255,255,0.03)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.03)_1px,transparent_1px)] [background-size:5rem_5rem] [mask-image:radial-gradient(circle_at_center,black,transparent_80%)]"></div>

        <main class="flex min-h-screen items-center justify-center px-4 py-10 sm:px-6 lg:px-8">
            <section class="w-full max-w-3xl rounded-[2rem] border border-white/8 bg-white/[0.04] px-6 py-8 shadow-[0_30px_120px_rgba(0,0,0,0.45)] backdrop-blur sm:px-8 sm:py-10">
                <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <x-storefront.brand-logo class="mb-4 block w-[9rem]" />
                        <p class="text-[0.76rem] uppercase tracking-[0.38em] text-amber-200/80">System response</p>
                        <h1 class="mt-3 text-4xl font-semibold text-white sm:text-5xl">{{ $headline }}</h1>
                        <p class="mt-4 max-w-2xl text-sm leading-7 text-ys-ivory/72 sm:text-base">{{ $copy }}</p>
                    </div>

                    <div class="inline-flex h-16 w-16 items-center justify-center rounded-full border border-amber-300/25 bg-amber-300/10 text-lg font-semibold text-amber-100">
                        {{ $status }}
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <a href="{{ $primaryActionUrl }}" class="ys-button-primary text-sm">{{ $primaryActionLabel }}</a>
                    <a href="{{ route('storefront.home') }}" class="ys-button-secondary text-sm">Return home</a>
                </div>
            </section>
        </main>
    </body>
</html>
