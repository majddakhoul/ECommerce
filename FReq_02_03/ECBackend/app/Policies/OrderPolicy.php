<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use App\Support\AccessControl;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, Order $order): bool
    {
        return $this->isAdmin($user) || $user->id === $order->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Order $order): bool
    {
        return $this->isAdmin($user);
    }

    public function cancel(User $user, Order $order): bool
    {
        return $this->isAdmin($user) || $user->id === $order->user_id;
    }

    public function delete(User $user, Order $order): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        return $user->hasRole(AccessControl::ADMIN_ROLE);
    }
}
