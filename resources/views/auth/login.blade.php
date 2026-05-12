@extends('layouts.auth', ['title' => 'Sign in | Ysabelle Retail'])

@section('content')
    <section class="ys-auth-shell">
        <div class="ys-auth-panel">
            <div class="ys-auth-header">
                <x-storefront.brand-logo class="mx-auto block w-[9.5rem]" />
                <h1 class="ys-auth-heading">Welcome back</h1>
                <p class="ys-auth-copy">
                    {{ $isAdminPortal ? 'Authorized staff sign in. Customer accounts will stay in the storefront account area.' : 'Sign in to continue with your Ysabelle account.' }}
                </p>
            </div>

            @if (session('status'))
                <div class="ys-auth-status" role="status">
                    {{ session('status') }}
                </div>
            @endif

            @if ($isAdminPortal)
                <div class="mb-6 rounded-[1.6rem] border border-amber-300/25 bg-amber-300/10 px-4 py-3 text-sm text-amber-100">
                    Admin access mode is active for this sign-in session.
                </div>
            @endif

            <form action="{{ route('login.store') }}" method="POST" class="ys-auth-form" novalidate>
                @csrf
                <input type="hidden" name="portal" value="{{ $portalIntent }}">
                @if (is_string($intendedUrl) && $intendedUrl !== '')
                    <input type="hidden" name="intended" value="{{ $intendedUrl }}">
                @endif

                <label class="ys-auth-field">
                    <span class="ys-auth-field-label">Email address</span>
                    <input
                        type="email"
                        name="email"
                        class="ys-auth-input"
                        value="{{ old('email') }}"
                        autocomplete="email"
                        inputmode="email"
                        required
                        autofocus
                        aria-describedby="@error('email') login-email-error @enderror"
                        aria-invalid="@error('email') true @else false @enderror"
                    >
                    @error('email')
                        <span id="login-email-error" class="ys-auth-error">{{ $message }}</span>
                    @enderror
                </label>

                <label class="ys-auth-field">
                    <span class="ys-auth-field-label">Password</span>
                    <span class="ys-auth-password-shell">
                        <input
                            id="login-password"
                            type="password"
                            name="password"
                            class="ys-auth-input ys-auth-input-password"
                            autocomplete="current-password"
                            required
                            data-password-source
                            aria-describedby="@error('password') login-password-error @enderror"
                            aria-invalid="@error('password') true @else false @enderror"
                        >
                        <button
                            type="button"
                            class="ys-auth-password-toggle"
                            data-password-toggle
                            aria-controls="login-password"
                            aria-label="Show password"
                        >
                            <span class="ys-auth-password-toggle-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                    <path d="M2 12s3.8-6 10-6 10 6 10 6-3.8 6-10 6-10-6-10-6Z" />
                                    <circle cx="12" cy="12" r="3.2" />
                                </svg>
                            </span>
                            <span class="ys-auth-sr-only" data-password-toggle-label>Show password</span>
                        </button>
                    </span>
                    @error('password')
                        <span id="login-password-error" class="ys-auth-error">{{ $message }}</span>
                    @enderror
                </label>

                <label class="ys-auth-check">
                    <input
                        type="checkbox"
                        name="remember"
                        value="1"
                        class="ys-auth-check-input"
                        @checked(old('remember'))
                    >
                    <span class="ys-auth-check-copy">Remember me on this device</span>
                </label>

                <button type="submit" class="ys-auth-submit">Continue</button>
            </form>

            <p class="ys-auth-helper-copy">
                <a href="{{ route('password.request') }}" class="ys-auth-inline-link">Forgot your password?</a>
            </p>

            <p class="ys-auth-switch-copy">
                Don't have an account?
                <a href="{{ route('register') }}" class="ys-auth-inline-link">Sign up</a>
            </p>

            <div class="ys-auth-divider" role="presentation">
                <span>or continue with</span>
            </div>

            @include('auth.partials.social-providers', ['providers' => $socialProviders])

            @include('auth.partials.legal-links')
        </div>
    </section>
@endsection
