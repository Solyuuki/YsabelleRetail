<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    private const ORDERS_PER_PAGE = 5;

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $orders = $user->orders()
            ->with([
                'items' => fn ($query) => $query
                    ->select([
                        'id',
                        'order_id',
                        'product_id',
                        'product_variant_id',
                        'product_name',
                        'variant_name',
                        'quantity',
                        'unit_price',
                        'line_total',
                        'metadata',
                    ])
                    ->with([
                        'product:id,name,slug,primary_image_url,image_alt,image_gallery',
                        'variant:id,product_id,name,option_values',
                        'variant.product:id,name,slug,primary_image_url,image_alt,image_gallery',
                    ]),
            ])
            ->latest('placed_at')
            ->latest()
            ->paginate(self::ORDERS_PER_PAGE);

        return view('storefront.account.index', [
            'orders' => $orders,
            'user' => $user,
            'latestOrderNumber' => session('order_success_number'),
        ]);
    }
}
