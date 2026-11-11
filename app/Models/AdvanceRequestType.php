<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdvanceRequestType extends Model
{
    protected $table = 'advance_request_types';
    protected $primaryKey = 'id';
    
    protected $fillable = [
        'type_code',
        'type_name',
        'access_mode',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function advanceRequestMasters()
    {
        return $this->hasMany(AdvanceRequestMaster::class, 'advance_type_id', 'id');
    }

    // Helper method to check if this type uses department access
    public function isDepartmentAccess()
    {
        return $this->access_mode === 'DEPARTMENT';
    }

    // Helper method to check if this type uses employee access
    public function isEmployeeAccess()
    {
        return $this->access_mode === 'EMPLOYEE';
    }
}