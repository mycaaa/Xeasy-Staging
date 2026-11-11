<?php

namespace App\Http\Requests;
use Illuminate\Validation\Rule;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Adjust authorization logic as needed
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'role_id' => 'nullable|array',
            'role_id.*' => 'integer|exists:roles,id',
            'is_active' => 'required|boolean',
            'name' => 'nullable|string|max:150',
            'username' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:150',
            'password' => 'nullable|string|min:6',
            'company_id' => 'nullable|string',
        ];
    }

    /**
     * Custom error messages.
     */
    public function messages(): array
    {
        return [
            'role_id.required' => 'At least one role must be selected.',
            'role_id.array' => 'Roles must be an array.',
            'role_id.*.integer' => 'Each role must be a valid integer.',
            'role_id.*.exists' => 'One or more selected roles are invalid.',
            'is_active.required' => 'User status must be selected.',
            'is_active.boolean' => 'User status must be true or false.',
        ];
    }
}
