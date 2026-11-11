<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentImportRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'payment_file' => [
                'required',
                'file',
                'mimes:xlsx,xls,csv',
                'max:10240' // 10MB max
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'payment_file.required' => 'Please select a file to upload.',
            'payment_file.file' => 'The uploaded file is not valid.',
            'payment_file.mimes' => 'The file must be an Excel (.xlsx, .xls) or CSV (.csv) file.',
            'payment_file.max' => 'The file size must not exceed 10MB.'
        ];
    }
}