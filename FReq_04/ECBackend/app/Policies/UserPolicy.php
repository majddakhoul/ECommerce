<?php

namespace App\Policies;

use App\Models\User;
use App\Support\AccessControl;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(AccessControl::ADMIN_ROLE);
    }

    public function view(User $user, User $model): bool
    {
        return $user->is($model) || $user->hasRole(AccessControl::ADMIN_ROLE);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(AccessControl::ADMIN_ROLE);
    }

    public function update(User $user, User $model): bool
    {
        return $user->is($model) || $user->hasRole(AccessControl::ADMIN_ROLE);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasRole(AccessControl::ADMIN_ROLE);
    }

    public function unlock(User $user, User $model): bool
    {
        return $user->hasRole(AccessControl::ADMIN_ROLE);
    }

    public function manageRole(User $user, User $model): bool
    {
        return $user->hasRole(AccessControl::ADMIN_ROLE);
    }
}
