@extends('layouts.auth', ['title' => 'Forgot password | Ysabelle Retail'])

@section('content')
    <section class="ys-auth-shell">
        <div class="ys-auth-panel">
            <div class="ys-auth-header">
                <x-storefront.brand-logo class="mx-auto block w-[9.5rem]" />
                <h1 class="ys-auth-heading">Reset your password</h1>
                <p class="ys-auth-copy">
                    Enter the email address tied to your manual Ysabelle account and we will send reset instructions if the account is eligible.
                </p>
            </div>

            @if (session('status'))
                <div class="ys-auth-status" role="status">
                    {{ session('status') }}
                </div>
            @endif

            <form action="{{ route('password.email') }}" method="POST" class="ys-auth-form" novalidate>
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

                <button type="submit" class="ys-auth-submit">Send reset instructions</button>
            </form>

            <p class="ys-auth-switch-copy">
                Remembered it?
                <a href="{{ route('login') }}" class="ys-auth-inline-link">Back to sign in</a>
            </p>

            @include('auth.partials.legal-links')
        </div>
    </section>
@endsection
