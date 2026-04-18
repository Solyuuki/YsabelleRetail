<?php

namespace App\Http\Controllers\Storefront\Cart;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class CartController extends Controller
{
    public function index(): View
    {
        return view('storefront.cart.index');
    }
}
