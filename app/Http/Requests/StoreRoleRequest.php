<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role_name' => 'required|string|max:255|unique:roles,name',
            'is_active' => 'required|boolean',
            'permissions' => 'sometimes|array'
        ];
    }

    public function messages()
    {
        return [
            'role_name.required' => 'The role name is required.',
            'role_name.unique' => 'The role name must be unique.',
            'role_name.string' => 'The role name must be a string.',
            'role_name.max' => 'The role name may not be greater than 255 characters.',
            'is_active.required' => 'The active status is required.',
            'is_active.boolean' => 'The active status must be true or false.',
        ];
    }
}
