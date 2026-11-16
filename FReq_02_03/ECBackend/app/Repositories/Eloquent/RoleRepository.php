<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Support\AccessControl;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class RoleRepository implements RoleRepositoryInterface
{
    public function all(): Collection
    {
        return Role::where('guard_name', AccessControl::GUARD)->withCount('users')->orderBy('id')->get();
    }

    public function findByName(string $name): ?Role
    {
        return Role::where('guard_name', AccessControl::GUARD)->where('name', $name)->first();
    }

    public function countUsers(Role $role): Role
    {
        return $role->loadCount('users');
    }

    public function create(string $name): Role
    {
        return Role::create(['name' => $name, 'guard_name' => AccessControl::GUARD]);
    }

    public function update(Role $role, string $name): Role
    {
        $role->update(['name' => $name]);

        return $role->refresh();
    }

    public function delete(Role $role): void
    {
        $role->delete();
    }
}
