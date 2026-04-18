<?php

use App\Http\Controllers\Api\V1\Catalog\CategoryController;
use App\Http\Controllers\Api\V1\Catalog\ProductController;
use App\Http\Controllers\Api\V1\System\StatusController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->as('api.v1.')
    ->group(function (): void {
        Route::get('/status', StatusController::class)->name('status');

        Route::prefix('catalog')
            ->as('catalog.')
            ->group(function (): void {
                Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
                Route::get('/categories/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');
                Route::get('/products', [ProductController::class, 'index'])->name('products.index');
                Route::get('/products/{product:slug}', [ProductController::class, 'show'])->name('products.show');
            });
    });
