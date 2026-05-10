<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if (! $request->user()?->hasVerifiedEmail()) {
            $request->fulfill();
        }

        return redirect()->route('storefront.account.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Email verified',
                'message' => 'Your email address has been verified successfully.',
            ]);
    }
}
