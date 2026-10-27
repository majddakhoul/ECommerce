<?php

namespace App\DTOs;

use App\Http\Requests\StoreUserRequest;
use App\Support\AccessControl;

class CreateUserDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly bool $isActive,
        public readonly string $role
    ) {}

    public static function fromRequest(StoreUserRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            email: strtolower($request->validated('email')),
            password: $request->validated('password'),
            isActive: (bool) ($request->validated('is_active') ?? true),
            role: $request->validated('role') ?? AccessControl::USER_ROLE
        );
    }

    public function attributes(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'is_active' => $this->isActive,
        ];
    }
}
