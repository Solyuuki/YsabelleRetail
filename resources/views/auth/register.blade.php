@extends('layouts.auth', ['title' => 'Create account | Ysabelle Retail'])

@section('content')
    <section class="ys-auth-shell">
        <div class="ys-auth-panel">
            <div class="ys-auth-header">
                <x-storefront.brand-logo class="mx-auto block w-[9.5rem]" />
                <h1 class="ys-auth-heading">Create an account</h1>
                <p class="ys-auth-copy">
                    Create your Ysabelle account to start shopping faster.
                </p>
            </div>

            <form action="{{ route('register.store') }}" method="POST" class="ys-auth-form" novalidate>
                @csrf
                @if (is_string($intendedUrl ?? null) && $intendedUrl !== '')
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
                        aria-describedby="@error('email') register-email-error @enderror"
                        aria-invalid="@error('email') true @else false @enderror"
                    >
                    @error('email')
                        <span id="register-email-error" class="ys-auth-error">{{ $message }}</span>
                    @enderror
                </label>

                <label class="ys-auth-field">
                    <span class="ys-auth-field-label">Display name</span>
                    <input
                        type="text"
                        name="name"
                        class="ys-auth-input"
                        value="{{ old('name') }}"
                        autocomplete="name"
                        required
                        aria-describedby="@error('name') register-name-error @enderror"
                        aria-invalid="@error('name') true @else false @enderror"
                    >
                    @error('name')
                        <span id="register-name-error" class="ys-auth-error">{{ $message }}</span>
                    @enderror
                </label>

                <label class="ys-auth-field">
                    <span class="ys-auth-field-label">Password</span>
                    <span class="ys-auth-password-shell">
                        <input
                            id="register-password"
                            type="password"
                            name="password"
                            class="ys-auth-input ys-auth-input-password"
                            autocomplete="new-password"
                            required
                            data-password-source
                            data-password-strength-source
                            aria-describedby="register-password-hint register-password-strength @error('password') register-password-error @enderror"
                            aria-invalid="@error('password') true @else false @enderror"
                        >
                        <button
                            type="button"
                            class="ys-auth-password-toggle"
                            data-password-toggle
                            aria-controls="register-password"
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
                    <span id="register-password-hint" class="ys-auth-hint">
                        Use at least 8 characters with at least one letter and one number.
                    </span>
                    <span
                        id="register-password-strength"
                        class="ys-auth-strength"
                        data-password-strength
                        data-password-strength-for="register-password"
                    >
                        <span class="ys-auth-strength-track" aria-hidden="true">
                            <span class="ys-auth-strength-bar"></span>
                        </span>
                        <span class="ys-auth-strength-copy">Strength: waiting for input</span>
                    </span>
                    @error('password')
                        <span id="register-password-error" class="ys-auth-error">{{ $message }}</span>
                    @enderror
                </label>

                <label class="ys-auth-field">
                    <span class="ys-auth-field-label">Confirm password</span>
                    <span class="ys-auth-password-shell">
                        <input
                            id="register-password-confirmation"
                            type="password"
                            name="password_confirmation"
                            class="ys-auth-input ys-auth-input-password"
                            autocomplete="new-password"
                            required
                            data-password-confirmation
                            data-password-confirm-for="register-password"
                            aria-describedby="register-password-match"
                        >
                        <button
                            type="button"
                            class="ys-auth-password-toggle"
                            data-password-toggle
                            aria-controls="register-password-confirmation"
                            aria-label="Show password confirmation"
                        >
                            <span class="ys-auth-password-toggle-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                    <path d="M2 12s3.8-6 10-6 10 6 10 6-3.8 6-10 6-10-6-10-6Z" />
                                    <circle cx="12" cy="12" r="3.2" />
                                </svg>
                            </span>
                            <span class="ys-auth-sr-only" data-password-toggle-label>Show password confirmation</span>
                        </button>
                    </span>
                    <span
                        id="register-password-match"
                        class="ys-auth-feedback"
                        data-password-match
                        data-password-match-for="register-password"
                    >
                        Re-enter your password to confirm it.
                    </span>
                </label>

                <button type="submit" class="ys-auth-submit">Create account</button>
            </form>

            <p class="ys-auth-switch-copy">
                Already have an account?
                <a
                    href="{{ is_string($intendedUrl ?? null) && $intendedUrl !== '' ? route('login', ['intended' => $intendedUrl]) : route('login') }}"
                    class="ys-auth-inline-link"
                >
                    Sign in
                </a>
            </p>

            <div class="ys-auth-divider" role="presentation">
                <span>or continue with</span>
            </div>

            @include('auth.partials.social-providers', ['providers' => $socialProviders])

            @include('auth.partials.legal-links')
        </div>
    </section>
@endsection
