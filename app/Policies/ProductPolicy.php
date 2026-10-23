<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProductPolicy
{
    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): Response
    {
        return ($user->role === UserRole::Seller || $user->role === UserRole::Admin)
            ? Response::allow()
            : Response::deny('Hanya pedagang (seller) yang dapat menambahkan produk.');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Product $product): Response
    {
        return ($user->id === $product->seller_id || $user->role === UserRole::Admin)
            ? Response::allow()
            : Response::deny('Anda tidak memiliki akses untuk mengubah produk ini.');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Product $product): Response
    {
        return ($user->id === $product->seller_id || $user->role === UserRole::Admin)
            ? Response::allow()
            : Response::deny('Anda tidak memiliki akses untuk menghapus produk ini.');
    }
}
