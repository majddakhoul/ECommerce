<?php

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;

class ProductFilterDTO
{
    public function __construct(
        public readonly ?string $search,
        public readonly ?string $category,
        public readonly ?bool $inStock,
        public readonly int $perPage
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            search: $request->validated('search'),
            category: CreateProductDTO::normalizeCategory($request->validated('category')),
            inStock: $request->filled('in_stock') ? $request->boolean('in_stock') : null,
            perPage: (int) ($request->validated('per_page') ?? config('api.default_per_page'))
        );
    }
}
