<?php

namespace App\DTOs;

use App\Http\Requests\AssignUserRoleRequest;

class AssignRoleDTO
{
    public function __construct(public readonly int $roleId) {}

    public static function fromRequest(AssignUserRoleRequest $request): self
    {
        return new self((int) $request->validated('role_id'));
    }
}
