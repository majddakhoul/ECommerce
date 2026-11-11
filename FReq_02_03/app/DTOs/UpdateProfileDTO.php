<?php

namespace App\DTOs;

use App\Http\Requests\UpdateProfileRequest;

class UpdateProfileDTO
{
    public function __construct(public readonly array $attributes) {}

    public static function fromRequest(UpdateProfileRequest $request): self
    {
        $attributes = $request->safe()->only(['name', 'email']);

        if (isset($attributes['email'])) {
            $attributes['email'] = strtolower($attributes['email']);
        }

        return new self($attributes);
    }
}
