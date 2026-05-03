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
                'facebook' => <<<'SVG'
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" class="ys-auth-provider-svg ys-auth-provider-svg-facebook">
                        <circle cx="12" cy="12" r="11" fill="#1877F2" />
                        <path fill="#FFFFFF" d="M13.43 20v-6.14h2.06l.31-2.39h-2.37v-1.53c0-.69.19-1.16 1.18-1.16H15.9V6.63c-.22-.03-.97-.09-1.85-.09-1.84 0-3.1 1.12-3.1 3.18v1.75H8.86v2.39h2.09V20z" />
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
