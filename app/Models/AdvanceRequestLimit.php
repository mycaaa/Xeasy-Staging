<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvanceRequestLimit extends Model
{
    use HasFactory;

    protected $fillable = [
        'advance_type_id',
        'department_id',
        'activity_id',
        'min_amount',
        'max_amount',
        'is_active',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Relationship with AdvanceRequestType
     */
    public function advanceType(): BelongsTo
    {
        return $this->belongsTo(AdvanceRequestType::class, 'advance_type_id');
    }

    /**
     * Relationship with CoreDepartments
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(CoreDepartments::class, 'department_id');
    }

    /**
     * Relationship with User (created by)
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship with User (updated by)
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope for active limits
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get limit for specific advance type, department and activity
     */
    public static function getLimitForActivity($advanceTypeId, $departmentId, $activityId = null)
    {
        return self::active()
                    ->where('advance_type_id', $advanceTypeId)
                    ->where('department_id', $departmentId)
                    ->where('activity_id', $activityId)
                    ->first();
    }

    /**
     * Get all limits for a department and advance type
     */
    public static function getLimitsForDepartment($advanceTypeId, $departmentId)
    {
        return self::active()
                    ->where('advance_type_id', $advanceTypeId)
                    ->where('department_id', $departmentId)
                    ->get();
    }
}