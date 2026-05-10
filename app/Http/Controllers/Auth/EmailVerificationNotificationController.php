<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        if ($request->user()?->hasVerifiedEmail()) {
            return redirect()->route('storefront.account.index');
        }

        $request->user()?->sendEmailVerificationNotification();

        return back()->with('status', 'A fresh verification link has been sent to your email address.');
    }
}
