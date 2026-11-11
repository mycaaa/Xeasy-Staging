<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAPIManagerRequest extends FormRequest
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
            'claim_id' => 'required|integer|unique:api_list,claim_id,' . $this->route('api_manager'),
            'api_name' => 'required|string|max:255',
            'endpoint' => 'required|string|url|max:500',
            'status' => 'required|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'claim_id.required' => 'Claim ID is required.',
            'claim_id.integer' => 'Claim ID must be an integer.',
            'claim_id.unique' => 'This Claim ID already exists.',

            'api_name.required' => 'API name is required.',
            'api_name.string' => 'API name must be a string.',
            'api_name.max' => 'API name cannot exceed 255 characters.',

            'endpoint.required' => 'API endpoint URL is required.',
            'endpoint.string' => 'Endpoint must be a string.',
            'endpoint.url' => 'Endpoint must be a valid URL.',
            'endpoint.max' => 'Endpoint cannot exceed 500 characters.',

            'status.required' => 'Status is required.',
            'status.boolean' => 'Status must be true or false.',
        ];
    }
}
