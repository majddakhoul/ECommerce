<?php

namespace App\Http\Controllers;

use App\DTOs\RoleDTO;
use App\DTOs\UserFilterDTO;
use App\Http\Requests\ListRoleUsersRequest;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Http\Resources\UserResource;
use App\Services\RoleService;
use App\Services\UserService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected RoleService $roleService,
        protected UserService $userService
    ) {}

    public function index()
    {
        $this->authorize('viewAny', Role::class);

        return $this->success(RoleResource::collection($this->roleService->list()));
    }

    public function show(Role $role)
    {
        $this->authorize('view', $role);

        return $this->success(new RoleResource($this->roleService->show($role)));
    }

    public function store(StoreRoleRequest $request)
    {
        return $this->created(
            new RoleResource($this->roleService->create(RoleDTO::fromRequest($request))),
            'Role created'
        );
    }

    public function update(UpdateRoleRequest $request, Role $role)
    {
        return $this->success(
            new RoleResource($this->roleService->update($role, RoleDTO::fromRequest($request))),
            'Role updated'
        );
    }

    public function destroy(Request $request, Role $role)
    {
        $this->authorize('delete', $role);

        $this->roleService->delete($role);

        return $this->noContent();
    }

    public function users(ListRoleUsersRequest $request, Role $role)
    {
        return $this->paginated(
            $this->userService->list(UserFilterDTO::fromRequest($request, $role->name)),
            UserResource::class
        );
    }
}
