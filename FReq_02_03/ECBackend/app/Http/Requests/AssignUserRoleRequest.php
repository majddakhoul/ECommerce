<?php

namespace App\Http\Requests;

use App\Support\AccessControl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageRole', $this->route('user'));
    }

    public function rules(): array
    {
        return [
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->where('guard_name', AccessControl::GUARD),
            ],
        ];
    }
}
