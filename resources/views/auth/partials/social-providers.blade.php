@php
    $providers = $providers ?? [];
@endphp

<div class="ys-auth-social-list" aria-label="Social sign in options">
    @foreach ($providers as $provider)
        @php
            $iconMarkup = match ($provider['key']) {
                'google' => <<<'SVG'
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" class="ys-auth-provider-svg">
                        <path fill="#EA4335" d="M12.22 10.2v3.69h5.14c-.22 1.2-.93 2.22-2 2.91l3.24 2.51c1.89-1.74 2.98-4.31 2.98-7.37 0-.7-.06-1.38-.19-2.03z" />
                        <path fill="#34A853" d="M12 22c2.7 0 4.97-.9 6.63-2.43l-3.24-2.51c-.9.6-2.05.95-3.39.95-2.61 0-4.83-1.76-5.62-4.13H3.03v2.59A10 10 0 0 0 12 22z" />
                        <path fill="#4A90E2" d="M6.38 13.88A6 6 0 0 1 6.07 12c0-.65.11-1.28.31-1.88V7.53H3.03A10 10 0 0 0 2 12c0 1.61.38 3.13 1.03 4.47z" />
                        <path fill="#FBBC05" d="M12 5.99c1.47 0 2.79.51 3.82 1.51l2.86-2.86C16.96 3.02 14.7 2 12 2a10 10 0 0 0-8.97 5.53l3.35 2.59C7.17 7.75 9.39 5.99 12 5.99z" />
                    </svg>
                SVG,
                'microsoft' => <<<'SVG'
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" class="ys-auth-provider-svg">
                        <rect x="3" y="3" width="8" height="8" fill="#F25022" rx="1" />
                        <rect x="13" y="3" width="8" height="8" fill="#7FBA00" rx="1" />
                        <rect x="3" y="13" width="8" height="8" fill="#00A4EF" rx="1" />
                        <rect x="13" y="13" width="8" height="8" fill="#FFB900" rx="1" />
                    </svg>
                SVG,
                'github' => <<<'SVG'
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" class="ys-auth-provider-svg">
                        <path fill="currentColor" d="M12 .5C5.65.5.5 5.65.5 12c0 5.09 3.29 9.41 7.86 10.94.58.11.79-.25.79-.56 0-.28-.01-1.02-.02-2-3.2.7-3.88-1.54-3.88-1.54-.52-1.33-1.28-1.68-1.28-1.68-1.05-.71.08-.7.08-.7 1.16.08 1.78 1.19 1.78 1.19 1.03 1.76 2.7 1.25 3.36.95.1-.75.4-1.25.73-1.53-2.55-.29-5.23-1.28-5.23-5.69 0-1.26.45-2.28 1.18-3.09-.12-.29-.51-1.46.11-3.05 0 0 .97-.31 3.17 1.18A10.9 10.9 0 0 1 12 6.03c.97 0 1.95.13 2.86.38 2.2-1.49 3.17-1.18 3.17-1.18.63 1.59.24 2.76.12 3.05.73.81 1.18 1.83 1.18 3.09 0 4.42-2.69 5.39-5.25 5.68.41.35.78 1.04.78 2.1 0 1.52-.01 2.74-.01 3.12 0 .31.21.67.8.56A11.5 11.5 0 0 0 23.5 12C23.5 5.65 18.35.5 12 .5Z" />
                    </svg>
                SVG,
                default => '',
            };
        @endphp

        @php
            $buttonClasses = 'ys-auth-social-button'.($provider['available'] ? '' : ' is-disabled');
        @endphp

        @if ($provider['available'])
            <a href="{{ $provider['href'] }}" class="{{ $buttonClasses }}">
                <span class="ys-auth-provider-icon" aria-hidden="true">{!! $iconMarkup !!}</span>
                <span class="ys-auth-provider-meta">
                    <span class="ys-auth-provider-title">{{ $provider['label'] }}</span>
                </span>
            </a>
        @else
            <button type="button" class="{{ $buttonClasses }}" disabled aria-disabled="true">
                <span class="ys-auth-provider-icon" aria-hidden="true">{!! $iconMarkup !!}</span>
                <span class="ys-auth-provider-meta">
                    <span class="ys-auth-provider-title">{{ $provider['label'] }}</span>
                    <span class="ys-auth-provider-status">{{ $provider['status'] ?? 'OAuth setup required' }}</span>
                </span>
            </button>
        @endif
    @endforeach
</div>
