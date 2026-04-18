<?php

namespace App\Policies\Orders;

use App\Models\Orders\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Order $order): bool
    {
        return $user->isAdmin() || $order->user_id === $user->id;
    }

    public function update(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }
}
