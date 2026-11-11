<?php

namespace App\DTOs;

use App\Http\Requests\ChangePasswordRequest;

class ChangePasswordDTO
{
    public function __construct(
        public readonly string $currentPassword,
        public readonly string $newPassword
    ) {}

    public static function fromRequest(ChangePasswordRequest $request): self
    {
        return new self(
            currentPassword: $request->validated('current_password'),
            newPassword: $request->validated('password')
        );
    }
}
