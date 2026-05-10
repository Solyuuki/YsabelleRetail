@extends('layouts.auth', ['title' => 'Set a new password | Ysabelle Retail'])

@section('content')
    <section class="ys-auth-shell">
        <div class="ys-auth-panel">
            <div class="ys-auth-header">
                <x-storefront.brand-logo class="mx-auto block w-[9.5rem]" />
                <h1 class="ys-auth-heading">Choose a new password</h1>
                <p class="ys-auth-copy">
                    Set a fresh password for your manual Ysabelle account. Your new password must follow our account security rules.
                </p>
            </div>

            @if (session('status'))
                <div class="ys-auth-status" role="status">
                    {{ session('status') }}
                </div>
            @endif

            <form action="{{ route('password.store') }}" method="POST" class="ys-auth-form" novalidate>
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <label class="ys-auth-field">
                    <span class="ys-auth-field-label">Email address</span>
                    <input
                        type="email"
                        name="email"
                        class="ys-auth-input"
                        value="{{ old('email', $email) }}"
                        autocomplete="email"
                        inputmode="email"
                        required
                        autofocus
                        aria-describedby="@error('email') reset-email-error @enderror"
                        aria-invalid="@error('email') true @else false @enderror"
                    >
                    @error('email')
                        <span id="reset-email-error" class="ys-auth-error">{{ $message }}</span>
                    @enderror
                </label>

                <label class="ys-auth-field">
                    <span class="ys-auth-field-label">New password</span>
                    <span class="ys-auth-password-shell">
                        <input
                            id="reset-password"
                            type="password"
                            name="password"
                            class="ys-auth-input ys-auth-input-password"
                            autocomplete="new-password"
                            required
                            data-password-source
                            data-password-strength-source
                            aria-describedby="reset-password-hint reset-password-strength @error('password') reset-password-error @enderror"
                            aria-invalid="@error('password') true @else false @enderror"
                        >
                        <button
                            type="button"
                            class="ys-auth-password-toggle"
                            data-password-toggle
                            aria-controls="reset-password"
                            aria-label="Show new password"
                        >
                            <span class="ys-auth-password-toggle-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                    <path d="M2 12s3.8-6 10-6 10 6 10 6-3.8 6-10 6-10-6-10-6Z" />
                                    <circle cx="12" cy="12" r="3.2" />
                                </svg>
                            </span>
                            <span class="ys-auth-sr-only" data-password-toggle-label>Show new password</span>
                        </button>
                    </span>
                    <span id="reset-password-hint" class="ys-auth-hint">
                        Use at least 8 characters with at least one letter and one number.
                    </span>
                    <span
                        id="reset-password-strength"
                        class="ys-auth-strength"
                        data-password-strength
                        data-password-strength-for="reset-password"
                    >
                        <span class="ys-auth-strength-track" aria-hidden="true">
                            <span class="ys-auth-strength-bar"></span>
                        </span>
                        <span class="ys-auth-strength-copy">Strength: waiting for input</span>
                    </span>
                    @error('password')
                        <span id="reset-password-error" class="ys-auth-error">{{ $message }}</span>
                    @enderror
                </label>

                <label class="ys-auth-field">
                    <span class="ys-auth-field-label">Confirm new password</span>
                    <span class="ys-auth-password-shell">
                        <input
                            id="reset-password-confirmation"
                            type="password"
                            name="password_confirmation"
                            class="ys-auth-input ys-auth-input-password"
                            autocomplete="new-password"
                            required
                            data-password-confirmation
                            data-password-confirm-for="reset-password"
                            aria-describedby="reset-password-match"
                        >
                        <button
                            type="button"
                            class="ys-auth-password-toggle"
                            data-password-toggle
                            aria-controls="reset-password-confirmation"
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
                        id="reset-password-match"
                        class="ys-auth-feedback"
                        data-password-match
                        data-password-match-for="reset-password"
                    >
                        Re-enter your password to confirm it.
                    </span>
                </label>

                <button type="submit" class="ys-auth-submit">Reset password</button>
            </form>

            <p class="ys-auth-switch-copy">
                Need to sign in instead?
                <a href="{{ route('login') }}" class="ys-auth-inline-link">Back to sign in</a>
            </p>

            @include('auth.partials.legal-links')
        </div>
    </section>
@endsection
