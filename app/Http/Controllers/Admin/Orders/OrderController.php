<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Orders\UpdateOrderLifecycleRequest;
use App\Models\Orders\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

    public function updateLifecycle(UpdateOrderLifecycleRequest $request, Order $order): RedirectResponse
    {
        $currentStatus = (string) $order->status;
        $currentPaymentStatus = (string) $order->payment_status;
        $targetStatus = (string) $request->validated('status');
        $targetPaymentStatus = (string) $request->validated('payment_status');

        $this->ensureLifecycleTransitionIsAllowed(
            order: $order,
            targetStatus: $targetStatus,
            targetPaymentStatus: $targetPaymentStatus,
        );

        $order->forceFill([
            'status' => $targetStatus,
            'payment_status' => $targetPaymentStatus,
            'fulfillment_status' => $targetStatus === 'completed' ? 'fulfilled' : 'unfulfilled',
        ])->save();

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('toast', [
                'type' => 'success',
                'title' => 'Order lifecycle updated',
                'message' => sprintf(
                    '%s moved from %s / %s to %s / %s.',
                    $order->order_number,
                    str($currentStatus)->headline(),
                    str($currentPaymentStatus)->headline(),
                    str($targetStatus)->headline(),
                    str($targetPaymentStatus)->headline(),
                ),
            ]);
    }

    private function ensureLifecycleTransitionIsAllowed(
        Order $order,
        string $targetStatus,
        string $targetPaymentStatus,
    ): void {
        $currentStatus = (string) $order->status;
        $currentPaymentStatus = (string) $order->payment_status;

        if ($currentStatus === 'completed' && ($targetStatus !== 'completed' || $targetPaymentStatus !== $currentPaymentStatus)) {
            throw ValidationException::withMessages([
                'status' => 'Completed orders are locked and cannot move back to an earlier lifecycle stage.',
            ]);
        }

        if ($currentPaymentStatus === 'paid' && $targetPaymentStatus !== 'paid') {
            throw ValidationException::withMessages([
                'payment_status' => 'Paid orders cannot be moved back to unpaid or pending.',
            ]);
        }

        if ($targetStatus === 'completed' && $targetPaymentStatus !== 'paid') {
            throw ValidationException::withMessages([
                'payment_status' => 'An order must be marked as paid before it can be completed.',
            ]);
        }

        $allowedTransitions = [
            'pending' => ['pending', 'processing', 'completed'],
            'processing' => ['processing', 'completed'],
            'completed' => ['completed'],
        ];

        if (! in_array($targetStatus, $allowedTransitions[$currentStatus] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => 'That lifecycle change is not allowed for the current order state.',
            ]);
        }
    }
}
