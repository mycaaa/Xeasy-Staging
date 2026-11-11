<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoreDepartments extends Model
{
    protected $connection = 'hrims';
    protected $table = 'core_departments';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = ['id', 'department_name', 'department_code', 'numeric_code', 'effective_date', 'is_active'];

    public function employees()
    {
        return $this->hasMany(HRMEmployees::class, 'DepartmentId', 'id')->join('hrm_employee_general', 'hrm_employee.EmployeeID', '=', 'hrm_employee_general.EmployeeID');
    }

    public function designations()
    {
        return $this->belongsToMany(
            CoreDesignation::class, 
            'core_designation_department_mapping', 
            'department_id', 
            'designation_id'
        );
    }

    public function advanceRequestMaster()
    {
        return $this->hasOne(AdvanceRequestMaster::class, 'department_id', 'id');
    }

    public static function active()
    {
        return self::where('is_active', 1)->orderBy('department_name')->get(['id', 'department_name as name']);
    }
}
