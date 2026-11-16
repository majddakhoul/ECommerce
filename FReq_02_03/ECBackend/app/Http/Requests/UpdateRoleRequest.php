<?php

namespace App\Http\Requests;

use App\Support\AccessControl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('role'));
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => strtolower(trim($this->input('name')))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:50',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique('roles', 'name')
                    ->where('guard_name', AccessControl::GUARD)
                    ->ignore($this->route('role')->id),
            ],
        ];
    }
}
