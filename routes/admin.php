<?php

use App\Http\Controllers\Admin\Catalog\CategoryController;
use App\Http\Controllers\Admin\Catalog\ProductController;
use App\Http\Controllers\Admin\Customers\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Inventory\BatchStockImportController;
use App\Http\Controllers\Admin\Inventory\InventoryController;
use App\Http\Controllers\Admin\Inventory\ManualStockImportController;
use App\Http\Controllers\Admin\Inventory\WalkInSaleController;
use App\Http\Controllers\Admin\Orders\OrderController;
use App\Http\Controllers\Admin\Realtime\ActivityFeedController;
use App\Http\Controllers\Admin\Reports\ReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'admin', 'prevent-back-history'])
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/realtime/feed', ActivityFeedController::class)->name('realtime.feed');

        Route::prefix('catalog')
            ->as('catalog.')
            ->group(function (): void {
                Route::resource('products', ProductController::class)->except(['show']);
                Route::resource('categories', CategoryController::class)->except(['show']);
            });

        Route::prefix('inventory')
            ->as('inventory.')
            ->group(function (): void {
                Route::get('/', [InventoryController::class, 'index'])->name('index');

                Route::get('/manual-import', [ManualStockImportController::class, 'create'])->name('manual-import.create');
                Route::post('/manual-import', [ManualStockImportController::class, 'store'])->name('manual-import.store');

                Route::get('/batch-imports', [BatchStockImportController::class, 'create'])->name('batch-imports.create');
                Route::post('/batch-imports/preview', [BatchStockImportController::class, 'preview'])->name('batch-imports.preview');
                Route::post('/batch-imports', [BatchStockImportController::class, 'store'])->name('batch-imports.store');
                Route::get('/batch-imports/template', [BatchStockImportController::class, 'template'])->name('batch-imports.template');
            });

        Route::prefix('pos')
            ->as('pos.')
            ->group(function (): void {
                Route::get('/', [WalkInSaleController::class, 'create'])->name('create');
                Route::post('/', [WalkInSaleController::class, 'store'])->name('store');
                Route::get('/search', [WalkInSaleController::class, 'search'])->name('search');
            });

        Route::prefix('orders')
            ->as('orders.')
            ->group(function (): void {
                Route::get('/', [OrderController::class, 'index'])->name('index');
                Route::get('/{order}', [OrderController::class, 'show'])->name('show');
            });

        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');

        Route::prefix('reports')
            ->as('reports.')
            ->group(function (): void {
                Route::get('/', [ReportController::class, 'index'])->name('index');
                Route::get('/export', [ReportController::class, 'export'])->name('export');
            });
    });
