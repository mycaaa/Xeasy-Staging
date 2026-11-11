<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdvanceRequestMaster extends Model
{
    protected $table = 'advance_request_masters';
    protected $primaryKey = 'id';

    protected $fillable = [
        'advance_type_id',
        'department_id',
        'designation_ids',
        'vertical_designation',
        'grade_ids',
        'vertical_ids',
        'management_data',
        'manager_ids',
        'expense_percentage',
        'employee_ids',
        'request_for',
        'approver_level',
        'effective_from',
        'effective_to',
        'is_active',
        'is_tour_travel',
        'allow_expense',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'designation_ids' => 'array',
        'grade_ids' => 'array',
        'vertical_ids' => 'array',
        'management_data' => 'array',
        'manager_ids' => 'array',
        'employee_ids' => 'array',
        'request_for' => 'array',
        'is_active' => 'boolean',
        'is_tour_travel' => 'boolean',
        'allow_expense' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'vertical_designation' => 'array'
    ];

    protected $attributes = [
        'designation_ids' => '[]',
        'grade_ids' => '[]',
        'vertical_ids' => '[]',
        'management_data' => '[]',
        'manager_ids' => '[]',
        'employee_ids' => '[]',
        'request_for' => '[]',
        'is_active' => false
    ];

    public function advanceType()
    {
        return $this->belongsTo(AdvanceRequestType::class, 'advance_type_id', 'id');
    }

    public function department()
    {
        return $this->belongsTo(CoreDepartments::class, 'department_id', 'id');
    }

    public function designations()
    {
        return $this->belongsToMany(
            CoreDesignation::class,
            null,  // No pivot table needed since we're using JSON
            null,
            null
        )->whereIn('id', $this->designation_ids ?? []);
    }

    public function employees()
    {
        return $this->belongsToMany(
            HRMEmployees::class,
            null,  // No pivot table needed since we're using JSON
            null,
            null
        )->whereIn('EmployeeID', $this->employee_ids ?? []);
    }

    public function creator()
    {
        return $this->belongsTo(HRMEmployees::class, 'created_by', 'EmployeeID');
    }

    public function updater()
    {
        return $this->belongsTo(HRMEmployees::class, 'updated_by', 'EmployeeID');
    }

    // Helper method to get designation names
    public function getDesignationNamesAttribute()
    {
        if (!$this->designation_ids) {
            return [];
        }

        return CoreDesignation::whereIn('id', $this->designation_ids)
            ->pluck('designation_name')
            ->toArray();
    }

    // Helper method to get grade names
    public function getGradeNamesAttribute()
    {
        if (!$this->grade_ids) {
            return [];
        }

        return CoreGrade::whereIn('id', $this->grade_ids)
            ->pluck('grade_name')
            ->toArray();
    }

    // Helper method to get employee names
    public function getEmployeeNamesAttribute()
    {
        if (!$this->employee_ids) {
            return [];
        }

        return HRMEmployees::whereIn('EmployeeID', $this->employee_ids)
            ->get()
            ->map(function ($emp) {
                return trim($emp->Fname . ' ' . $emp->Sname . ' ' . $emp->Lname);
            })
            ->toArray();
    }

    // Helper method to get formatted effective from date
    public function getFormattedEffectiveFromAttribute()
    {
        if (!$this->effective_from) {
            return '';
        }

        return \Carbon\Carbon::parse($this->effective_from)->format('Y-m-d');
    }

    // Helper method to get formatted effective to date
    public function getFormattedEffectiveToAttribute()
    {
        if (!$this->effective_to) {
            return '';
        }

        return \Carbon\Carbon::parse($this->effective_to)->format('Y-m-d');
    }

    // Helper method to check if a designation is selected
    public function hasDesignation($designationId)
    {
        return in_array($designationId, $this->designation_ids ?? []);
    }

    // Helper method to check if a grade is selected
    public function hasGrade($gradeId)
    {
        return in_array($gradeId, $this->grade_ids ?? []);
    }

    // Helper method to check if an employee is selected
    public function hasEmployee($employeeId)
    {
        return in_array($employeeId, $this->employee_ids ?? []);
    }
}
