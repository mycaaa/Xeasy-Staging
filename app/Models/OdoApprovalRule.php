<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OdoApprovalRule extends Model
{
    use HasFactory;

    protected $table = 'odo_approval_rules';

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'auto_approve_if_below_limit' => 'string',
        'dept_active' => 'string',
        'is_active' => 'boolean',
    ];

    protected $fillable = [
        'department_id',
        'vertical_ids',
        'monthly_km_limit',
        'auto_approve_if_below_limit',
        'dept_active',
        'effective_from',
        'effective_to',
        'is_active'
    ];

    public function department()
    {
        return $this->belongsTo(CoreDepartments::class, 'department_id');
    }
}