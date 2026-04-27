@extends('layouts.storefront', ['title' => 'Sign in | Ysabelle Retail'])

@section('content')
    <section class="ys-container flex min-h-[calc(100vh-20rem)] items-center justify-center pb-18 pt-10 lg:pt-14">
        <div class="w-full max-w-md rounded-[1.9rem] border border-white/7 bg-ys-panel/90 p-8 shadow-[0_20px_80px_rgba(0,0,0,0.5)]" data-reveal>
            <x-storefront.brand-logo class="mx-auto block w-[10rem]" />
            <h1 class="mt-5 text-center font-serif text-4xl text-ys-ivory">Welcome back</h1>
            <p class="mt-3 text-center text-sm text-ys-ivory/48">Sign in to access your bag and orders.</p>

            <form action="{{ route('login.store') }}" method="POST" class="mt-8 space-y-5">
                @csrf
                <label class="ys-field">
                    <span>Email</span>
                    <input type="email" name="email" class="ys-input" value="{{ old('email') }}" required>
                </label>

                <label class="ys-field">
                    <span>Password</span>
                    <input type="password" name="password" class="ys-input" required>
                </label>

                <button class="ys-button-primary mt-2 w-full justify-center">Sign in</button>
            </form>

            <p class="mt-5 text-center text-sm text-ys-ivory/42">
                Don't have an account?
                <a href="{{ route('register') }}" class="font-semibold text-ys-ivory transition hover:text-ys-gold">Sign up</a>
            </p>
        </div>
    </section>
@endsection
