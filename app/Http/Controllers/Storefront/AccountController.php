<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $orders = $user->orders()
            ->with('items')
            ->latest('placed_at')
            ->latest()
            ->get();

        return view('storefront.account.index', [
            'orders' => $orders,
            'user' => $user,
            'latestOrderNumber' => session('order_success_number'),
        ]);
    }
}
