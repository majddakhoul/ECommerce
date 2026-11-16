<?php

namespace App\Services;

use App\DTOs\AssignRoleDTO;
use App\DTOs\CreateUserDTO;
use App\DTOs\UpdateUserDTO;
use App\DTOs\UserFilterDTO;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\AccessControl;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\Permission\Models\Role;

class UserService
{
    public function __construct(protected UserRepositoryInterface $userRepo) {}

    public function list(UserFilterDTO $filter): LengthAwarePaginator
    {
        return $this->userRepo->paginate($filter);
    }

    public function show(User $user): User
    {
        return $user->load('roles');
    }

    public function create(CreateUserDTO $dto): User
    {
        $user = $this->userRepo->create($dto->attributes());
        $user->syncRoles([$dto->role]);

        return $user->load('roles');
    }

    public function update(User $user, UpdateUserDTO $dto, User $actor): User
    {
        $attributes = $dto->attributes;

        if (!$this->isAdmin($actor)) {
            unset($attributes['is_active'], $attributes['password']);
        }

        if ($actor->is($user)) {
            unset($attributes['password']);

            if (array_key_exists('is_active', $attributes) && !$attributes['is_active']) {
                throw ApiException::conflict('You cannot deactivate your own account.');
            }
        }

        if (array_key_exists('is_active', $attributes) && !$attributes['is_active']) {
            $this->assertNotLastAdmin($user);
        }

        return $this->userRepo->update($user, $attributes)->load('roles');
    }

    public function delete(User $user, User $actor): void
    {
        if ($actor->is($user)) {
            throw ApiException::forbidden('You cannot delete your own account.');
        }

        $this->assertNotLastAdmin($user);

        $this->userRepo->delete($user);
    }

    public function unlock(User $user): User
    {
        return $this->userRepo->unlock($user)->load('roles');
    }

    public function assignRole(User $user, AssignRoleDTO $dto, User $actor): User
    {
        $this->assertNotSelf($user, $actor);

        $role = Role::where('guard_name', AccessControl::GUARD)->findOrFail($dto->roleId);

        if ($user->hasRole($role)) {
            throw ApiException::conflict('The user already has this role.');
        }

        $user->assignRole($role);

        return $user->load('roles');
    }

    public function removeRole(User $user, Role $role, User $actor): User
    {
        $this->assertNotSelf($user, $actor);

        if (!$user->hasRole($role)) {
            throw ApiException::conflict('The user does not have this role.');
        }

        if ($role->name === AccessControl::ADMIN_ROLE) {
            $this->assertNotLastAdmin($user);
        }

        $user->removeRole($role);

        return $user->load('roles');
    }

    private function assertNotSelf(User $user, User $actor): void
    {
        if ($actor->is($user)) {
            throw ApiException::forbidden('You cannot change your own roles.');
        }
    }

    private function isAdmin(User $user): bool
    {
        return $user->hasRole(AccessControl::ADMIN_ROLE);
    }

    private function assertNotLastAdmin(User $target): void
    {
        if (!$this->isAdmin($target)) {
            return;
        }

        if (User::role(AccessControl::ADMIN_ROLE, AccessControl::GUARD)->count() <= 1) {
            throw ApiException::conflict('The last administrator cannot be removed, demoted or deactivated.');
        }
    }
}
