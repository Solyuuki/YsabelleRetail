@extends('layouts.auth', ['title' => 'Sign in | Ysabelle Retail'])

@section('content')
    <section class="ys-auth-shell">
        <div class="ys-auth-panel">
            <div class="ys-auth-header">
                <x-storefront.brand-logo class="mx-auto block w-[9.5rem]" />
                <h1 class="ys-auth-heading">Welcome back</h1>
                <p class="ys-auth-copy">
                    Sign in to continue with your Ysabelle account.
                </p>
            </div>

            <form action="{{ route('login.store') }}" method="POST" class="ys-auth-form" novalidate>
                @csrf

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
                        aria-invalid="@error('email') true @else false @enderror"
                    >
                    @error('email')
                        <span class="ys-auth-error">{{ $message }}</span>
                    @enderror
                </label>

                <label class="ys-auth-field">
                    <span class="ys-auth-field-label">Password</span>
                    <input
                        type="password"
                        name="password"
                        class="ys-auth-input"
                        autocomplete="current-password"
                        required
                        aria-invalid="@error('password') true @else false @enderror"
                    >
                    @error('password')
                        <span class="ys-auth-error">{{ $message }}</span>
                    @enderror
                </label>

                <button type="submit" class="ys-auth-submit">Continue</button>
            </form>

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
