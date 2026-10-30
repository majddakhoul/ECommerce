<?php

namespace App\DTOs;

use App\Http\Requests\StoreProductRequest;
use Illuminate\Http\UploadedFile;

class CreateProductDTO
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $description,
        public readonly float $price,
        public readonly float $cost,
        public readonly int $stock,
        public readonly ?string $category,
        public readonly ?UploadedFile $image
    ) {}

    public static function fromRequest(StoreProductRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            description: $request->validated('description'),
            price: (float) $request->validated('price'),
            cost: (float) ($request->validated('cost') ?? 0),
            stock: (int) ($request->validated('stock') ?? 0),
            category: self::normalizeCategory($request->validated('category')),
            image: $request->file('image')
        );
    }

    public function attributes(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'cost' => $this->cost,
            'stock' => $this->stock,
            'category' => $this->category,
        ];
    }

    public static function normalizeCategory(?string $category): ?string
    {
        $category = $category === null ? null : strtolower(trim($category));

        return $category === '' ? null : $category;
    }
}
