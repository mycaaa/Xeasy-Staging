<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HRMEmployees extends Model
{
    protected $connection = 'hrims';
    protected $table = 'hrm_employee';
    protected $primaryKey = 'EmployeeID';
    public $timestamps = false;
    
    protected $fillable = [
        'EmpCode',
        'Fname',
        'Sname', 
        'Lname',
        'EmpStatus'
    ];

    // Helper method to get full name
    public function getFullNameAttribute()
    {
        return trim($this->Fname . ' ' . $this->Sname . ' ' . $this->Lname);
    }

    // Scope for active employees
    public function scopeActive($query)
    {
        return $query->whereIn('EmpStatus', ['A', 'R']);
    }
}