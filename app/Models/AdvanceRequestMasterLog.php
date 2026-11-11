<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdvanceRequestMasterLog extends Model
{
    protected $table = 'advance_request_master_logs';
    
    protected $fillable = [
        'advance_request_master_id',
        'advance_type_id',
        'department_id',
        'employee_id',
        'action',
        'old_data',
        'new_data',
        'changes_summary',
        'changed_by'
    ];
    
    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    public function advanceRequestMaster()
    {
        return $this->belongsTo(AdvanceRequestMaster::class);
    }
    
    public function advanceType()
    {
        return $this->belongsTo(AdvanceRequestType::class);
    }
    
    public function department()
    {
        return $this->belongsTo(CoreDepartments::class);
    }
    
    public function changedBy()
    {
        return $this->belongsTo(HRMEmployees::class, 'changed_by', 'EmployeeID');
    }
}
