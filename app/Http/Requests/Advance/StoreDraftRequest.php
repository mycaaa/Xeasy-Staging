<?php

namespace App\Http\Requests\Advance;

use Illuminate\Foundation\Http\FormRequest;

class StoreDraftRequest extends FormRequest
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
    /** FX-53: a foreign-currency line may come without an INR amount; work it out first. */
    protected function prepareForValidation(): void
    {
        $emps = $this->input('employees');
        if (! is_array($emps)) {
            return;
        }
        foreach ($emps as $i => $e) {
            foreach ((array) ($e['activities'] ?? []) as $j => $a) {
                if (! \App\Services\Advance\ForeignCurrencyAdvance::isForeign($a['currency'] ?? null)) {
                    continue;
                }
                try {
                    $emps[$i]['activities'][$j] = \App\Services\Advance\ForeignCurrencyAdvance::normalize($a);
                } catch (\RuntimeException $ex) {
                    $emps[$i]['activities'][$j]['fx_error'] = $ex->getMessage();
                }
            }
        }
        $this->merge(['employees' => $emps]);
    }

    public function rules(): array
    {
        return [
            'request.activity_name_id' => 'nullable|integer|exists:claimtype,ClaimId',
            'request.advance_type_id' => 'required|integer',
            'request.month' => 'required|integer|between:1,12',
            'request.year_id' => 'required|integer',
            'request.req_app_submit_id' => 'required|integer',
            'request.advance_fill_date' => 'nullable|date',
            
            'employees' => 'required|array|min:1',
            'employees.*.employee_id' => 'required|integer',
            
            'employees.*.activities' => 'required|array|min:1',
            'employees.*.activities.*.activity_type_id' => 'nullable|integer',
            'employees.*.activities.*.activity_crops_id' => 'nullable|array',
            'employees.*.activities.*.activity_variety_id' => 'nullable|array',
            'employees.*.activities.*.amount' => 'required|numeric|min:0.01',
            'employees.*.activities.*.purpose' => 'required|string|max:1000',
            'employees.*.activities.*.is_tour_travel' => 'nullable|string|in:Y,N',
            // FX-53: foreign-currency advance; amount is then filled as INR equivalent
            'employees.*.activities.*.currency' => 'nullable|string|size:3',
            'employees.*.activities.*.fx_amount' => 'nullable|numeric|min:0.01',
            'employees.*.activities.*.fx_rate' => 'nullable|numeric|min:0.0001',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'request.activity_name_id.exists' => 'Selected activity name is invalid',
            'request.advance_type_id.required' => 'Advance type is required',
            'request.month.required' => 'Month is required',
            'request.month.between' => 'Month must be between 1 and 12',
            'request.year_id.required' => 'Year is required',
            'request.req_app_submit_id.required' => 'Request app submit ID is required',
            
            'employees.required' => 'At least one employee is required',
            'employees.*.employee_id.required' => 'Employee ID is required for all employees',
            
            'employees.*.activities.required' => 'At least one activity is required for each employee',
            'employees.*.activities.*.amount.required' => 'Activity amount is required',
            'employees.*.activities.*.amount.min' => 'Activity amount must be greater than 0',
            'employees.*.activities.*.purpose.required' => 'Purpose is required for all activities',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Check if activity_name_id is required based on advance_type_id
            $advanceTypeId = $this->input('request.advance_type_id');
            $activityNameId = $this->input('request.activity_name_id');
            
            // For Activity advance type (assuming ID 1), activity_name_id is required
            if ($advanceTypeId == 1 && empty($activityNameId)) {
                $validator->errors()->add('request.activity_name_id', 'Activity name is required for Activity advance type');
            }
            
            // Validate that crops and varieties are valid JSON arrays if provided
            $employees = $this->input('employees', []);
            foreach ($employees as $empIndex => $employee) {
                $activities = $employee['activities'] ?? [];
                foreach ($activities as $actIndex => $activity) {
                    // FX-53: foreign-currency line without a usable amount/rate
                    if (! empty($activity['fx_error'])) {
                        $validator->errors()->add("employees.{$empIndex}.activities.{$actIndex}.currency", $activity['fx_error']);
                    }
                    // For Activity advance type, activity_type_id is required
                    if ($advanceTypeId == 1 && empty($activity['activity_type_id'])) {
                        $validator->errors()->add("employees.{$empIndex}.activities.{$actIndex}.activity_type_id", 'Activity type is required for Activity advance');
                    }
                    
                    // Validate crops JSON
                    if (isset($activity['activity_crops_id']) && !empty($activity['activity_crops_id'])) {
                        if (!is_array($activity['activity_crops_id'])) {
                            $validator->errors()->add("employees.{$empIndex}.activities.{$actIndex}.activity_crops_id", 'Crops must be an array');
                        }
                    }
                    
                    // Validate varieties JSON
                    if (isset($activity['activity_variety_id']) && !empty($activity['activity_variety_id'])) {
                        if (!is_array($activity['activity_variety_id'])) {
                            $validator->errors()->add("employees.{$empIndex}.activities.{$actIndex}.activity_variety_id", 'Varieties must be an array');
                        }
                    }
                }
            }
        });
    }
}