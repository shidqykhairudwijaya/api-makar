<?php

namespace App\Http\Policies;

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Determine whether the user can view any orders.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the specific order.
     */
    public function view(User $user, Order $order): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        if ($user->role === UserRole::Buyer && $order->buyer_id === $user->id) {
            return true;
        }

        if ($user->role === UserRole::Courier) {
            return $order->courier_id === $user->id || $order->courier_id === null;
        }

        if ($user->role === UserRole::Seller) {
            return $order->items()->where('seller_id', $user->id)->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can cancel the order.
     */
    public function cancel(User $user, Order $order): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $user->role === UserRole::Buyer && $order->buyer_id === $user->id;
    }
}
