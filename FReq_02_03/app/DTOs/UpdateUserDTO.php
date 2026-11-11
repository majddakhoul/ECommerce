<?php

namespace App\DTOs;

use App\Http\Requests\UpdateUserRequest;

class UpdateUserDTO
{
    public function __construct(public readonly array $attributes) {}

    public static function fromRequest(UpdateUserRequest $request): self
    {
        $attributes = $request->safe()->only(['name', 'email', 'password', 'is_active']);

        if (isset($attributes['email'])) {
            $attributes['email'] = strtolower($attributes['email']);
        }

        return new self($attributes);
    }
}
