<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use App\Support\AccessControl;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Product $product): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(AccessControl::ADMIN_ROLE);
    }

    public function update(User $user, Product $product): bool
    {
        return $user->hasRole(AccessControl::ADMIN_ROLE);
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->hasRole(AccessControl::ADMIN_ROLE);
    }
}
