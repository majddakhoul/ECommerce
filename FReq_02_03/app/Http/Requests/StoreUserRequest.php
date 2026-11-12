<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\AccessControl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(8)->letters()->numbers()],
            'is_active' => ['sometimes', 'boolean'],
            'role' => ['sometimes', Rule::exists('roles', 'name')->where('guard_name', AccessControl::GUARD)],
        ];
    }
}
