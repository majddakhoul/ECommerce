<?php

namespace App\DTOs;

use App\Http\Requests\UpdateProductRequest;
use Illuminate\Http\UploadedFile;

class UpdateProductDTO
{
    public function __construct(
        public readonly array $attributes,
        public readonly ?UploadedFile $image
    ) {}

    public static function fromRequest(UpdateProductRequest $request): self
    {
        $attributes = $request->safe()->only(['name', 'description', 'price', 'cost', 'stock', 'category']);

        if (array_key_exists('category', $attributes)) {
            $attributes['category'] = CreateProductDTO::normalizeCategory($attributes['category']);
        }

        return new self($attributes, $request->file('image'));
    }
}
