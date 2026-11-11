<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMenuRequest extends FormRequest
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
    public function rules()
    {
        return [
            'menu_name' => ['required', 'string', 'max:255', 'unique:menus,title'],
            'parent_id' => ['nullable', 'exists:menus,id'],
            'icon' => ['nullable', 'string', 'max:255'],
            'order' => ['nullable', 'integer', 'min:0'],
            'url' => ['required', 'string', 'max:255'],
            'permission' => ['nullable', 'exists:permissions,permission_key'],
            'company_access_id' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages()
    {
        return [
            'menu_name.required' => 'The menu name is required.',
            'menu_name.string' => 'The menu name must be a string.',
            'menu_name.max' => 'The menu name may not be greater than 255 characters.',
            'menu_name.unique' => 'The menu name has already been taken.',
            'parent_id.exists' => 'The selected parent menu is invalid.',
            'icon.string' => 'The icon must be a string.',
            'icon.max' => 'The icon may not be greater than 255 characters.',
            'order.integer' => 'The order must be an integer.',
            'order.min' => 'The order must be at least 0.',
            'url.required' => 'The menu URL is required.',
            'url.string' => 'The menu URL must be a string.',
            'url.max' => 'The menu URL may not be greater than 255 characters.',
            'permission.exists' => 'The selected permission is invalid.',
            'is_active.required' => 'The active status is required.',
            'is_active.boolean' => 'The active status must be true or false.',
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'menu_name' => $this->input('menu_name'),
            'parent_id' => $this->input('parent_id') ?: null,
            'icon' => $this->input('icon') ?: null,
            'order' => $this->input('order') ?: 0,
            'url' => $this->input('url'),
            'permission' => $this->input('permission') ?: null,
            'is_active' => $this->input('is_active', 0),
            'company_access_id' => $this->input('company_access_id') ?: null,
        ]);
    }
}
