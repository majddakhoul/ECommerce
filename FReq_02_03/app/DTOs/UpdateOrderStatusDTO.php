<?php

namespace App\DTOs;

use App\Http\Requests\UpdateOrderStatusRequest;

class UpdateOrderStatusDTO
{
    public function __construct(public readonly string $status) {}

    public static function fromRequest(UpdateOrderStatusRequest $request): self
    {
        return new self($request->validated('status'));
    }
}
