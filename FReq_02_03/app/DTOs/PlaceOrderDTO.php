<?php

namespace App\DTOs;

use App\Http\Requests\PlaceOrderRequest;

class PlaceOrderDTO
{
    public function __construct(public readonly array $items) {}

    public static function fromRequest(PlaceOrderRequest $request): self
    {
        $items = [];

        foreach ($request->validated('items') as $item) {
            $productId = (int) $item['product_id'];
            $items[$productId] = ($items[$productId] ?? 0) + (int) $item['quantity'];
        }

        return new self($items);
    }
}
