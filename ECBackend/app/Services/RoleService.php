<?php

namespace App\Services;

use App\DTOs\RoleDTO;
use App\Exceptions\ApiException;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Support\AccessControl;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class RoleService
{
    public function __construct(protected RoleRepositoryInterface $roleRepo) {}

    public function list(): Collection
    {
        return $this->roleRepo->all();
    }

    public function show(Role $role): Role
    {
        return $this->roleRepo->countUsers($role);
    }

    public function create(RoleDTO $dto): Role
    {
        return $this->roleRepo->countUsers($this->roleRepo->create($dto->name));
    }

    public function update(Role $role, RoleDTO $dto): Role
    {
        $this->assertNotProtected($role, 'renamed');

        return $this->roleRepo->countUsers($this->roleRepo->update($role, $dto->name));
    }

    public function delete(Role $role): void
    {
        $this->assertNotProtected($role, 'deleted');

        if ($role->users()->exists()) {
            throw ApiException::conflict('This role is assigned to users and cannot be deleted.');
        }

        $this->roleRepo->delete($role);
    }

    private function assertNotProtected(Role $role, string $action): void
    {
        if (in_array($role->name, AccessControl::ROLES, true)) {
            throw ApiException::conflict("The built-in role '{$role->name}' cannot be {$action}.");
        }
    }
}
