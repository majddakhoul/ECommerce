<?php

namespace App\Policies;

use App\Models\User;
use App\Support\AccessControl;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, Role $role): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, Role $role): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, Role $role): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        return $user->hasRole(AccessControl::ADMIN_ROLE);
    }
}
