<?php

namespace App\Http\Requests;

use App\Support\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('order'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(OrderStatus::ALL)],
        ];
    }
}
