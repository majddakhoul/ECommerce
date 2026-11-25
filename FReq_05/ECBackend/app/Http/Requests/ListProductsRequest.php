<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class ListProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Product::class);
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'in_stock' => ['sometimes', 'nullable', 'boolean'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:' . config('api.max_per_page')],
        ];
    }
}
