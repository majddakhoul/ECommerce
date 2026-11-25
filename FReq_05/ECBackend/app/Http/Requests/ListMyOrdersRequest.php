<?php

namespace App\Http\Requests;

use App\Support\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMyOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', 'string', Rule::in(OrderStatus::ALL)],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:' . config('api.max_per_page')],
        ];
    }
}
