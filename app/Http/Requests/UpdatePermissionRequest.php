<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePermissionRequest extends FormRequest
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
            'permission_name' => ['required', 'string', 'max:255', Rule::unique('permissions', 'name')->ignore($this->route('permission'))],
            'group_id' => 'required|exists:permission_groups,id',
            'is_active' => 'required|boolean',
        ];
    }
    public function messages()
    {
        return [
            'permission_name.required' => 'The permission name is required.',
            'permission_name.string' => 'The permission name must be a string.',
            'permission_name.max' => 'The permission name may not be greater than 255 characters.',
            'group_id.required' => 'The group is required.',
            'group_id.exists' => 'The selected group does not exist.',
            'is_active.required' => 'The active status is required.',
            'is_active.boolean' => 'The active status must be true or false.',
        ];
    }
}
