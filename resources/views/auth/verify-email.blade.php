@extends('layouts.auth', ['title' => 'Verify your email | Ysabelle Retail'])

@section('content')
    <section class="ys-auth-shell">
        <div class="ys-auth-panel">
            <div class="ys-auth-header">
                <x-storefront.brand-logo class="mx-auto block w-[9.5rem]" />
                <h1 class="ys-auth-heading">Verify your email</h1>
                <p class="ys-auth-copy">
                    We sent a verification link to your email address. Open that message to confirm your manual Ysabelle account before relying on it for long-term account recovery.
                </p>
            </div>

            @if (session('status'))
                <div class="ys-auth-status" role="status">
                    {{ session('status') }}
                </div>
            @endif

            <form action="{{ route('verification.send') }}" method="POST" class="ys-auth-form">
                @csrf
                <button type="submit" class="ys-auth-submit">Resend verification email</button>
            </form>

            <form action="{{ route('logout') }}" method="POST" class="ys-auth-form ys-auth-form-compact" data-session-exit-form>
                @csrf
                <button type="submit" class="ys-auth-secondary-submit" data-loading-label="Signing out...">Sign out</button>
            </form>

            @include('auth.partials.legal-links')
        </div>
    </section>
@endsection
