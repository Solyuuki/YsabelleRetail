<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    private const GENERIC_STATUS = 'If this email can receive reset instructions, we will send reset instructions shortly.';

    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        $user = User::query()
            ->where('email', $request->string('email')->toString())
            ->first();

        if ($user && $user->hasLocalPassword()) {
            Password::broker()->sendResetLink([
                'email' => $user->email,
            ]);
        }

        return back()->with('status', self::GENERIC_STATUS);
    }
}
