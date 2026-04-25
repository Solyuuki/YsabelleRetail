<?php

namespace App\Providers;

use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Orders\Order;
use App\Policies\Catalog\CategoryPolicy;
use App\Policies\Catalog\ProductPolicy;
use App\Policies\Orders\OrderPolicy;
use App\View\Composers\StorefrontLayoutComposer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function ($user) {
            return $user->hasRole('super-admin') ? true : null;
        });

        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);

        View::composer([
            'layouts.storefront',
            'storefront.*',
            'auth.*',
        ], StorefrontLayoutComposer::class);
    }
}
