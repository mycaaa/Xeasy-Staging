<?php

namespace App\Http\Requests\Advance;

use Illuminate\Foundation\Http\FormRequest;

class FinanceApproveRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Add role-based authorization as needed
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'employee_id' => 'required|integer',
            'request_id' => 'required|integer|exists:advance_requests,id',
            'activity_id' => 'nullable|integer|exists:advance_request_activities,id',
            'finance_approved_amount' => 'nullable|numeric|min:0.01',
            'finance_remark' => 'nullable|string|max:1000',
            'fx_paid_rate' => 'nullable|numeric|min:0.0001', // FX-53
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'Employee ID is required',
            'request_id.required' => 'Request ID is required',
            'request_id.exists' => 'Invalid request ID',
            'activity_id.exists' => 'Invalid activity ID',
            'finance_approved_amount.min' => 'Finance approved amount must be greater than 0',
            'finance_remark.max' => 'Remarks cannot exceed 1000 characters',
        ];
    }
}