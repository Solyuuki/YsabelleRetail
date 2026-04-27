<?php

namespace App\Http\Controllers\Admin\Customers;

use App\Http\Controllers\Controller;
use App\Models\Orders\Order;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $registeredCustomers = User::query()
            ->withCount('orders')
            ->withSum('orders', 'grand_total')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10, ['*'], 'users_page')
            ->withQueryString();

        $walkInCustomers = Order::query()
            ->select([
                'customer_name',
                'customer_phone',
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('SUM(grand_total) as total_spend'),
                DB::raw('MAX(placed_at) as latest_sale_at'),
            ])
            ->where('source', 'walk_in')
            ->whereNotNull('customer_name')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder
                        ->where('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_phone', 'like', "%{$search}%");
                });
            })
            ->groupBy('customer_name', 'customer_phone')
            ->orderByDesc('latest_sale_at')
            ->paginate(10, ['*'], 'walkins_page')
            ->withQueryString();

        return view('admin.customers.index', [
            'registeredCustomers' => $registeredCustomers,
            'walkInCustomers' => $walkInCustomers,
            'search' => $search,
        ]);
    }
}
