<?php

namespace App\Http\Requests\Advance;

use Illuminate\Foundation\Http\FormRequest;

class RejectRequest extends FormRequest
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
            // New employee-wise rejection fields
            'employee_id' => 'required|integer',
            'request_id' => 'required|integer|exists:advance_requests,id',
            'remarks' => 'required|string|max:1000',
            
            // Legacy activity-based rejection (for backward compatibility)
            'activity_id' => 'nullable|integer|exists:advance_request_activities,id',
            'approver_remark' => 'nullable|string|max:1000',
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
            'remarks.required' => 'Rejection reason is required',
            'remarks.max' => 'Remarks cannot exceed 1000 characters',
            'activity_id.exists' => 'Invalid activity ID',
            'approver_remark.max' => 'Remarks cannot exceed 1000 characters',
        ];
    }
}