<?php

use App\Http\Controllers\Auth\LoginPageController;
use App\Http\Controllers\Auth\RegisterPageController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', LoginPageController::class)->name('login');
    Route::get('/register', RegisterPageController::class)->name('register');
});
