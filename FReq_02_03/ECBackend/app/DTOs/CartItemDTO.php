<?php

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;

class CartItemDTO
{
    public function __construct(
        public readonly ?int $productId,
        public readonly int $quantity
    ) {}

    public static function fromRequest(FormRequest $request, ?int $productId = null): self
    {
        return new self(
            productId: $productId ?? (int) $request->validated('product_id'),
            quantity: (int) $request->validated('quantity')
        );
    }
}
