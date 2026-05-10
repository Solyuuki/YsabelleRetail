@php
    $versionedPublicAsset = static function (string $path): string {
        $absolutePath = public_path($path);

        if (! file_exists($absolutePath)) {
            return asset($path);
        }

        return asset($path).'?v='.filemtime($absolutePath);
    };

    $actions = collect($actions ?? [])->filter(fn (mixed $action): bool => is_array($action));
    $previousUrl = url()->previous();
    $currentUrl = url()->current();
    $backUrl = filled($previousUrl) && $previousUrl !== $currentUrl
        ? $previousUrl
        : route('storefront.home');
    $brandLogo = $versionedPublicAsset('brand/yr-logo-full-transparent.png');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>{{ $title ?? 'Service status | Ysabelle Retail' }}</title>
        @include('partials.icon-head')
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link
            href="https://fonts.bunny.net/css?family=cormorant-garamond:500,600,700|instrument-sans:400,500,600,700"
            rel="stylesheet"
        />
        <style>
            :root {
                color-scheme: dark;
                --ys-bg: #050505;
                --ys-surface: rgba(255, 255, 255, 0.05);
                --ys-surface-strong: rgba(255, 255, 255, 0.08);
                --ys-border: rgba(255, 255, 255, 0.1);
                --ys-text: #f5f1e8;
                --ys-text-soft: rgba(245, 241, 232, 0.74);
                --ys-text-muted: rgba(245, 241, 232, 0.54);
                --ys-gold: #d7ad48;
                --ys-gold-deep: #b78221;
                --ys-gold-soft: rgba(215, 173, 72, 0.14);
                --ys-shadow: 0 32px 120px rgba(0, 0, 0, 0.42);
            }

            * {
                box-sizing: border-box;
            }

            html,
            body {
                margin: 0;
                min-height: 100%;
                background:
                    radial-gradient(circle at top, rgba(212, 166, 59, 0.18), transparent 24%),
                    radial-gradient(circle at bottom right, rgba(122, 84, 23, 0.16), transparent 30%),
                    linear-gradient(180deg, #090909 0%, #040404 52%, #020202 100%);
                color: var(--ys-text);
                font-family: "Instrument Sans", sans-serif;
            }

            body {
                position: relative;
                overflow-x: hidden;
            }

            body::before {
                content: "";
                position: fixed;
                inset: 0;
                pointer-events: none;
                opacity: 0.6;
                background-image:
                    linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                    linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
                background-size: 5rem 5rem;
                mask-image: radial-gradient(circle at center, black, transparent 80%);
            }

            a {
                color: inherit;
                text-decoration: none;
            }

            .ys-error-shell {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: clamp(1rem, 2vw, 1.5rem);
            }

            .ys-error-card {
                width: min(100%, 64rem);
                position: relative;
                overflow: hidden;
                border-radius: 1.75rem;
                border: 1px solid var(--ys-border);
                background:
                    linear-gradient(180deg, rgba(255, 255, 255, 0.06), rgba(255, 255, 255, 0.03)),
                    radial-gradient(circle at top right, rgba(215, 173, 72, 0.12), transparent 28%);
                box-shadow: var(--ys-shadow);
                backdrop-filter: blur(18px);
            }

            .ys-error-card::before {
                content: "";
                position: absolute;
                inset: 0 auto auto 0;
                width: 100%;
                height: 1px;
                background: linear-gradient(90deg, transparent, rgba(212, 166, 59, 0.62), transparent);
            }

            .ys-error-grid {
                display: grid;
                gap: 1.5rem;
                padding: clamp(1.25rem, 2.4vw, 1.85rem);
            }

            .ys-error-topbar {
                display: flex;
                justify-content: space-between;
                gap: 1.25rem;
                align-items: center;
            }

            .ys-error-brand-wrap {
                display: flex;
                align-items: center;
                width: clamp(10rem, 18vw, 13.5rem);
                max-width: 100%;
                min-height: 2.7rem;
                aspect-ratio: 2004 / 456;
                flex: 0 1 auto;
            }

            .ys-error-brand-image {
                display: block;
                width: 100%;
                max-width: 100%;
                max-height: 3.1rem;
                height: auto;
                object-fit: contain;
                object-position: left center;
            }

            .ys-error-status {
                min-width: 3.75rem;
                min-height: 3.75rem;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: 999px;
                border: 1px solid rgba(212, 166, 59, 0.24);
                background: rgba(212, 166, 59, 0.1);
                color: #f5deb0;
                font-size: 0.98rem;
                font-weight: 700;
                letter-spacing: 0.12em;
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.06);
            }

            .ys-error-kicker {
                margin: 0;
                color: rgba(245, 222, 176, 0.84);
                font-size: 0.75rem;
                font-weight: 700;
                letter-spacing: 0.34em;
                text-transform: uppercase;
            }

            .ys-error-headline {
                margin: 0.85rem 0 0;
                font-family: "Cormorant Garamond", serif;
                font-size: clamp(2.55rem, 7vw, 4.6rem);
                line-height: 0.98;
                letter-spacing: -0.035em;
            }

            .ys-error-copy {
                max-width: 40rem;
                margin: 0.85rem 0 0;
                color: var(--ys-text-soft);
                font-size: 0.98rem;
                line-height: 1.76;
            }

            .ys-error-meta {
                display: flex;
                flex-wrap: wrap;
                gap: 0.85rem;
                margin-top: 1.4rem;
            }

            .ys-error-pill {
                display: inline-flex;
                align-items: center;
                gap: 0.55rem;
                padding: 0.7rem 0.95rem;
                border-radius: 999px;
                border: 1px solid rgba(255, 255, 255, 0.09);
                background: rgba(255, 255, 255, 0.03);
                color: var(--ys-text-muted);
                font-size: 0.88rem;
                line-height: 1.4;
            }

            .ys-error-pill-dot {
                width: 0.55rem;
                height: 0.55rem;
                flex: 0 0 auto;
                border-radius: 999px;
                background: linear-gradient(180deg, var(--ys-gold), var(--ys-gold-deep));
                box-shadow: 0 0 18px rgba(215, 173, 72, 0.35);
            }

            .ys-error-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 0.9rem;
                margin-top: 1.85rem;
            }

            .ys-error-action {
                min-height: 3.35rem;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: 999px;
                padding: 0.9rem 1.35rem;
                font-size: 0.96rem;
                font-weight: 700;
                transition: transform 180ms ease, border-color 180ms ease, background-color 180ms ease, color 180ms ease;
            }

            .ys-error-action:hover {
                transform: translateY(-1px);
            }

            .ys-error-action-primary {
                border: 1px solid rgba(215, 173, 72, 0.7);
                background: linear-gradient(90deg, var(--ys-gold), var(--ys-gold-deep));
                color: #17110a;
                box-shadow: 0 18px 34px rgba(212, 166, 59, 0.18);
            }

            .ys-error-action-secondary {
                border: 1px solid rgba(255, 255, 255, 0.1);
                background: rgba(255, 255, 255, 0.03);
                color: var(--ys-text);
            }

            .ys-error-action-secondary:hover {
                border-color: rgba(215, 173, 72, 0.32);
                color: #f7d98c;
            }

            .ys-error-guidance {
                display: grid;
                gap: 1rem;
            }

            .ys-error-guidance-card {
                border-radius: 1.4rem;
                border: 1px solid rgba(255, 255, 255, 0.09);
                background: rgba(0, 0, 0, 0.18);
                padding: 1rem 1.1rem;
            }

            .ys-error-guidance-title {
                margin: 0;
                color: var(--ys-text);
                font-size: 0.95rem;
                font-weight: 700;
            }

            .ys-error-guidance-copy {
                margin: 0.45rem 0 0;
                color: var(--ys-text-muted);
                font-size: 0.9rem;
                line-height: 1.65;
            }

            .ys-error-footer {
                display: flex;
                flex-wrap: wrap;
                justify-content: space-between;
                gap: 1rem;
                align-items: center;
                padding-top: 1.25rem;
                border-top: 1px solid rgba(255, 255, 255, 0.08);
                color: var(--ys-text-muted);
                font-size: 0.85rem;
            }

            @media (min-width: 960px) {
                .ys-error-grid {
                    grid-template-columns: minmax(0, 1.25fr) minmax(17rem, 0.75fr);
                    align-items: start;
                }

                .ys-error-topbar,
                .ys-error-footer {
                    grid-column: 1 / -1;
                }

                .ys-error-guidance {
                    margin-top: 0.35rem;
                }
            }

            @media (max-width: 767px) {
                .ys-error-shell {
                    padding: 1rem;
                }

                .ys-error-grid {
                    padding: 1.25rem;
                    gap: 1.2rem;
                }

                .ys-error-topbar {
                    flex-direction: column;
                    align-items: flex-start;
                }

                .ys-error-status {
                    min-width: 3.7rem;
                    min-height: 3.7rem;
                    font-size: 0.9rem;
                }

                .ys-error-actions {
                    flex-direction: column;
                }

                .ys-error-action {
                    width: 100%;
                }

                .ys-error-brand-wrap {
                    width: min(11.25rem, 68vw);
                    min-height: 2.4rem;
                }

                .ys-error-brand-image {
                    max-height: 2.5rem;
                }
            }

            @media (prefers-reduced-motion: reduce) {
                .ys-error-action {
                    transition: none;
                }
            }
        </style>
    </head>
    <body>
        <main class="ys-error-shell">
            <section class="ys-error-card" aria-labelledby="error-headline">
                <div class="ys-error-grid">
                    <div class="ys-error-topbar">
                        <span class="ys-error-brand-wrap" aria-label="Ysabelle Retail">
                            <img
                                src="{{ $brandLogo }}"
                                alt="YR | Ysabelle Retail Shop"
                                width="2004"
                                height="456"
                                class="ys-error-brand-image"
                                loading="eager"
                                decoding="async"
                            >
                        </span>
                        <div class="ys-error-status" aria-label="HTTP status {{ $status }}">
                            {{ $status }}
                        </div>
                    </div>

                    <div>
                        <p class="ys-error-kicker">{{ $eyebrow ?? 'Service response' }}</p>
                        <h1 id="error-headline" class="ys-error-headline">{{ $headline }}</h1>
                        <p class="ys-error-copy">{{ $copy }}</p>

                        @if (! empty($chips ?? []))
                            <div class="ys-error-meta" aria-label="Recovery guidance">
                                @foreach ($chips as $chip)
                                    <span class="ys-error-pill">
                                        <span class="ys-error-pill-dot" aria-hidden="true"></span>
                                        {{ $chip }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="ys-error-guidance">
                        <article class="ys-error-guidance-card">
                            <h2 class="ys-error-guidance-title">{{ $guidanceTitle ?? 'What to do next' }}</h2>
                            <p class="ys-error-guidance-copy">
                                {{ $guidanceCopy ?? 'Use one of the recovery actions below to move forward safely.' }}
                            </p>
                        </article>
                    </div>

                    <div class="ys-error-actions">
                        @foreach ($actions as $action)
                            @php
                                $href = $action['url'] ?? route('storefront.home');
                                $variant = ($action['variant'] ?? 'secondary') === 'primary'
                                    ? 'ys-error-action-primary'
                                    : 'ys-error-action-secondary';
                            @endphp
                            <a
                                href="{{ $href }}"
                                class="ys-error-action {{ $variant }}"
                            >
                                {{ $action['label'] ?? 'Continue' }}
                            </a>
                        @endforeach

                        <a href="{{ $backUrl }}" class="ys-error-action ys-error-action-secondary">Go back</a>
                    </div>

                    <footer class="ys-error-footer">
                        <span>Ysabelle Retail keeps protected actions server-authoritative and safely recoverable.</span>
                        <span>No private system details are shown on this page.</span>
                    </footer>
                </div>
            </section>
        </main>
    </body>
</html>
