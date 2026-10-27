<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListRoleUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('role'));
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:' . config('api.max_per_page')],
        ];
    }
}
