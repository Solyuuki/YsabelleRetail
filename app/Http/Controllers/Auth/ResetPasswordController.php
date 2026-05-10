<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ResetPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'email' => $request->query('email', ''),
            'token' => $token,
        ]);
    }

    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        $user = User::query()
            ->where('email', $request->string('email')->toString())
            ->first();

        if (! $user || ! $user->hasLocalPassword()) {
            return back()
                ->withErrors([
                    'email' => 'This password reset link is invalid or has expired.',
                ])
                ->onlyInput('email');
        }

        $status = Password::broker()->reset(
            $request->credentials(),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'has_local_password' => true,
                    'remember_token' => Str::random(60),
                ])->save();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withErrors([
                    'email' => 'This password reset link is invalid or has expired.',
                ])
                ->onlyInput('email');
        }

        if (Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('login')
            ->with('status', 'Your password has been reset. You can now sign in.');
    }
}
