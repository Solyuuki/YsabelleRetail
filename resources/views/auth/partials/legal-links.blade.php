@php
    $termsRoute = collect(['terms', 'storefront.terms'])
        ->first(fn (string $routeName): bool => \Illuminate\Support\Facades\Route::has($routeName));

    $privacyRoute = collect(['privacy', 'storefront.privacy'])
        ->first(fn (string $routeName): bool => \Illuminate\Support\Facades\Route::has($routeName));
@endphp

<div class="ys-auth-legal" aria-label="Legal agreement notice">
    <p class="ys-auth-legal-copy">By continuing, you agree to our store policies.</p>

    <p class="ys-auth-legal-links">
        @if ($termsRoute)
            <a href="{{ route($termsRoute) }}" class="ys-auth-legal-link">Terms of Use</a>
        @else
            <span class="ys-auth-legal-muted">Terms of Use</span>
        @endif

        <span class="ys-auth-legal-separator" aria-hidden="true">&middot;</span>

        @if ($privacyRoute)
            <a href="{{ route($privacyRoute) }}" class="ys-auth-legal-link">Privacy Policy</a>
        @else
            <span class="ys-auth-legal-muted">Privacy Policy</span>
        @endif
    </p>
</div>
