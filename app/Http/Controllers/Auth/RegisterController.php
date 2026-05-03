<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Access\Role;
use App\Models\User;
use App\Services\Auth\SocialAuthService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    public function create(Request $request, SocialAuthService $socialAuth): View
    {
        return view('auth.register', [
            'socialProviders' => $socialAuth->providerButtons($request),
        ]);
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = User::query()->create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
            'status' => 'active',
        ]);

        $user->profile()->create([
            'preferred_name' => $user->name,
        ]);

        $customerRole = Role::query()->where('slug', 'customer')->first();

        if ($customerRole) {
            $user->roles()->syncWithoutDetaching([$customerRole->id]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('storefront.account.index')
            ->with('toast', [
                'type' => 'success',
                'title' => 'Account created',
                'message' => 'Your Ysabelle Retail account is ready.',
            ]);
    }
}
