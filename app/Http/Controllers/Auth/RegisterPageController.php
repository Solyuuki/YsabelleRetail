<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class RegisterPageController extends Controller
{
    public function __invoke(): View
    {
        return view('auth.register');
    }
}
