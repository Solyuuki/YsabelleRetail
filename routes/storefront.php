<?php

use App\Http\Controllers\Storefront\AccountController;
use App\Http\Controllers\Storefront\Cart\CartController;
use App\Http\Controllers\Storefront\Catalog\CategoryController;
use App\Http\Controllers\Storefront\Catalog\ProductController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\SupportPageController;
use App\Http\Controllers\Storefront\StorefrontAssistantController;
use App\Http\Controllers\Storefront\StorefrontVisualSearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('storefront.home');
Route::get('/shop', [ProductController::class, 'index'])->name('storefront.shop');
Route::prefix('support')
    ->as('storefront.support.')
    ->group(function (): void {
        Route::get('/size-guide', SupportPageController::class)->defaults('page', 'size-guide')->name('size-guide');
        Route::get('/shipping', SupportPageController::class)->defaults('page', 'shipping')->name('shipping');
        Route::get('/returns', SupportPageController::class)->defaults('page', 'returns')->name('returns');
        Route::get('/contact', SupportPageController::class)->defaults('page', 'contact')->name('contact');
    });

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

Route::prefix('assistant')
    ->as('storefront.assistant.')
    ->group(function (): void {
        Route::post('/message', [StorefrontAssistantController::class, 'message'])->name('message');
        Route::post('/message/stream', [StorefrontAssistantController::class, 'stream'])->name('message.stream');
        Route::post('/visual-search', StorefrontVisualSearchController::class)->name('visual-search');
    });

Route::middleware(['auth', 'customer'])->group(function (): void {
    Route::get('/checkout', [CheckoutController::class, 'create'])->name('storefront.checkout.create');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('storefront.checkout.store');
    Route::get('/account', AccountController::class)->name('storefront.account.index');
});
