<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
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
        if ($this->boolean('is_guest')) {
            return [
                'name' => 'required|string|max:150',
                'username' => 'required|string|max:100',
                'email' => 'nullable|email|max:150',
                'password' => 'required|string|min:6',
                'company_id' => 'required|string',
                'role_id' => 'nullable|array',
                'role_id.*' => 'exists:roles,id',
                'is_active' => 'required|boolean',
            ];
        }

        return [
            'employee_id' => 'required|string',
            'role_id' => 'required|array',
            'role_id.*' => 'exists:roles,id',
            'is_active' => 'required|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Guest user full name is required.',
            'username.required' => 'Guest username / ID is required.',
            'password.required' => 'Password is required for guest user.',
            'password.min' => 'Password must be at least 6 characters.',
            'company_id.required' => 'At least one company access must be selected.',
            'employee_id.required' => 'Employee ID is required.',
            'employee_id.unique' => 'This employee already has a user account.',
            'role_id.required' => 'At least one role must be selected.',
            'role_id.*.exists' => 'One or more selected roles are invalid.',
            'is_active.required' => 'User status must be selected.',
        ];
    }
}
