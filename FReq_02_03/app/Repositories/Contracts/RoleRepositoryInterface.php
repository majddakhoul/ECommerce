<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

interface RoleRepositoryInterface
{
    public function all(): Collection;

    public function findByName(string $name): ?Role;

    public function countUsers(Role $role): Role;

    public function create(string $name): Role;

    public function update(Role $role, string $name): Role;

    public function delete(Role $role): void;
}
