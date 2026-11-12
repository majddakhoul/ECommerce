<?php

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;

class OrderFilterDTO
{
    public function __construct(
        public readonly ?int $userId,
        public readonly ?string $status,
        public readonly int $perPage
    ) {}

    public static function fromRequest(FormRequest $request, ?int $userId = null): self
    {
        return new self(
            userId: $userId ?? ($request->filled('user_id') ? (int) $request->validated('user_id') : null),
            status: $request->validated('status'),
            perPage: (int) ($request->validated('per_page') ?? config('api.default_per_page'))
        );
    }
}
