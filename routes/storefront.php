<?php

use App\Http\Controllers\Storefront\Cart\CartController;
use App\Http\Controllers\Storefront\AccountController;
use App\Http\Controllers\Storefront\Catalog\CategoryController;
use App\Http\Controllers\Storefront\Catalog\ProductController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('storefront.home');
Route::get('/shop', [ProductController::class, 'index'])->name('storefront.shop');

Route::prefix('catalog')
    ->as('storefront.catalog.')
    ->group(function (): void {
        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::get('/categories/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/products/{product:slug}', [ProductController::class, 'show'])->name('products.show');
    });

Route::prefix('cart')
    ->as('storefront.cart.')
    ->group(function (): void {
        Route::get('/', [CartController::class, 'index'])->name('index');
        Route::post('/', [CartController::class, 'store'])->name('store');
        Route::patch('/items/{item}', [CartController::class, 'update'])->name('items.update');
        Route::delete('/items/{item}', [CartController::class, 'destroy'])->name('items.destroy');
    });

Route::middleware('auth')->group(function (): void {
    Route::get('/checkout', [CheckoutController::class, 'create'])->name('storefront.checkout.create');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('storefront.checkout.store');
    Route::get('/account', AccountController::class)->name('storefront.account.index');
    });
