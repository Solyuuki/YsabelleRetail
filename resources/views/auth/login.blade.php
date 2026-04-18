@extends('layouts.app', ['title' => 'Login'])

@section('content')
    <div class="mx-auto max-w-3xl rounded-3xl border border-white/10 bg-white/5 p-8">
        <p class="text-sm uppercase tracking-[0.3em] text-amber-300">Authentication</p>
        <h1 class="mt-3 text-3xl font-semibold text-white">Login route is reserved and wired.</h1>
        <p class="mt-4 text-stone-300">
            The application now has explicit auth routing boundaries so admin redirects and future frontend integration have stable endpoints.
            Credential handling, password reset, and session hardening flows are not implemented yet.
        </p>
    </div>
@endsection
