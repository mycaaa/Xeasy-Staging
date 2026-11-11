<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveConfigRequest extends FormRequest
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
            'company_id' => 'required|integer',
            'db_name' => ['required', 'string', Rule::in(['hrims', 'expense'])],
            'db_connection' => 'required|string|max:50',
            'db_host' => 'required|string|max:255',
            'db_port' => 'required|integer|min:1|max:65535',
            'db_database' => 'required|string|max:255',
            'db_username' => 'required|string|max:255',
            'db_password' => 'nullable|string|max:255',
            'is_active' => 'required|boolean',
        ];
    }
    public function messages()
    {
        return [
            'company_id.required' => 'The company ID is required.',
            'company_id.integer' => 'The company ID must be an integer.',
            'company_id.exists' => 'The selected company ID does not exist.',
            'db_name.required' => 'The database name is required.',
            'db_name.in' => 'The database name must be either "hrims" or "expense".',
            'db_connection.required' => 'The database connection type is required.',
            'db_connection.max' => 'The database connection type must not exceed 50 characters.',
            'db_host.required' => 'The database host is required.',
            'db_host.max' => 'The database host must not exceed 255 characters.',
            'db_port.required' => 'The database port is required.',
            'db_port.integer' => 'The database port must be an integer.',
            'db_port.min' => 'The database port must be at least 1.',
            'db_port.max' => 'The database port must not exceed 65535.',
            'db_database.required' => 'The database name is required.',
            'db_database.max' => 'The database name must not exceed 255 characters.',
            'db_username.required' => 'The database username is required.',
            'db_username.max' => 'The database username must not exceed 255 characters.',
            'db_password.max' => 'The database password must not exceed 255 characters.',
            'status.required' => 'The active status is required.',
            'status.boolean' => 'The active status must be true or false.',
        ];
    }


}
