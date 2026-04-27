<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Http\Controllers\Controller;
use App\Models\Orders\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $source = $request->query('source', 'all');
        $status = $request->query('status', 'all');

        $orders = Order::query()
            ->with(['handledBy', 'payments'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder
                        ->where('order_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_phone', 'like', "%{$search}%");
                });
            })
            ->when($source !== 'all', fn ($query) => $query->where('source', $source))
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest('placed_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'filters' => compact('search', 'source', 'status'),
        ]);
    }

    public function show(Order $order): View
    {
        return view('admin.orders.show', [
            'order' => $order->load(['items.product', 'items.variant', 'payments', 'handledBy', 'stockMovements.variant']),
        ]);
    }
}
