<?php

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;

class UserFilterDTO
{
    public function __construct(
        public readonly ?string $search,
        public readonly ?string $role,
        public readonly ?bool $isActive,
        public readonly int $perPage
    ) {}

    public static function fromRequest(FormRequest $request, ?string $role = null): self
    {
        return new self(
            search: $request->validated('search'),
            role: $role ?? $request->validated('role'),
            isActive: $request->has('is_active') ? $request->boolean('is_active') : null,
            perPage: (int) ($request->validated('per_page') ?? config('api.default_per_page'))
        );
    }
}
