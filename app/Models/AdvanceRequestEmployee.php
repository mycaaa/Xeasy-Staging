<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdvanceRequestEmployee extends Model
{
    use SoftDeletes;

    protected $table = 'advance_request_employees';

    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    /**
     * Get the advance request
     */
    public function advanceRequest()
    {
        return $this->belongsTo(AdvanceRequest::class, 'advance_request_id');
    }

    /**
     * Get activities for this employee
     */
    public function activities()
    {
        return $this->hasMany(AdvanceRequestActivity::class, 'advance_request_employee_id');
    }

    /**
     * Get employee details from HRIMS (if needed)
     */
    public function employee()
    {
        // This would connect to your HRIMS employee table
        // return $this->belongsTo(HRMEmployee::class, 'employee_id');
    }
}