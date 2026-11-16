<?php

namespace App\Http\Controllers;

use App\DTOs\AssignRoleDTO;
use App\DTOs\CreateUserDTO;
use App\DTOs\UpdateUserDTO;
use App\DTOs\UserFilterDTO;
use App\Http\Requests\AssignUserRoleRequest;
use App\Http\Requests\ListUsersRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use ApiResponse;

    public function __construct(protected UserService $userService) {}

    public function index(ListUsersRequest $request)
    {
        return $this->paginated(
            $this->userService->list(UserFilterDTO::fromRequest($request)),
            UserResource::class
        );
    }

    public function show(User $user)
    {
        $this->authorize('view', $user);

        return $this->success(new UserResource($this->userService->show($user)));
    }

    public function store(StoreUserRequest $request)
    {
        $user = $this->userService->create(CreateUserDTO::fromRequest($request));

        return $this->created(new UserResource($user), 'User created');
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $user = $this->userService->update($user, UpdateUserDTO::fromRequest($request), $request->user());

        return $this->success(new UserResource($user), 'User updated');
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorize('delete', $user);

        $this->userService->delete($user, $request->user());

        return $this->noContent();
    }

    public function unlock(User $user)
    {
        $this->authorize('unlock', $user);

        return $this->success(new UserResource($this->userService->unlock($user)), 'User unlocked');
    }

    public function assignRole(AssignUserRoleRequest $request, User $user)
    {
        $user = $this->userService->assignRole($user, AssignRoleDTO::fromRequest($request), $request->user());

        return $this->success(new UserResource($user), 'Role assigned');
    }

    public function removeRole(Request $request, User $user, Role $role)
    {
        $this->authorize('manageRole', $user);

        return $this->success(
            new UserResource($this->userService->removeRole($user, $role, $request->user())),
            'Role removed'
        );
    }
}
