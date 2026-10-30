<?php

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;

class RoleDTO
{
    public function __construct(public readonly string $name) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(strtolower(trim($request->validated('name'))));
    }
}
