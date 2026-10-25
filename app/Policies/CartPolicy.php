<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Cart;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CartPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Cart $cart): Response
    {
        return ($user->id === $cart->buyer_id || $user->role === UserRole::Admin)
            ? Response::allow()
            : Response::deny('Anda tidak memiliki akses ke item keranjang ini.');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Cart $cart): Response
    {
        return ($user->id === $cart->buyer_id || $user->role === UserRole::Admin)
            ? Response::allow()
            : Response::deny('Anda tidak memiliki akses untuk mengubah item keranjang ini.');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Cart $cart): Response
    {
        return ($user->id === $cart->buyer_id || $user->role === UserRole::Admin)
            ? Response::allow()
            : Response::deny('Anda tidak memiliki akses untuk menghapus item keranjang ini.');
    }
}
