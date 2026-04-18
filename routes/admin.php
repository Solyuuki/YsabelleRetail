<?php

use App\Http\Controllers\Admin\Catalog\CategoryController;
use App\Http\Controllers\Admin\Catalog\ProductController;
use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'admin'])
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::prefix('catalog')
            ->as('catalog.')
            ->group(function (): void {
                Route::get('/products', [ProductController::class, 'index'])->name('products.index');
                Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
            });
    });
