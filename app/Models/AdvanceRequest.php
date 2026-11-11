<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class AdvanceRequest extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'advance_requests';

    protected $guarded = ['id'];

    protected $casts = [
        'advance_fill_date' => 'date',
        'last_submitted_at' => 'datetime',
        'reverted_at' => 'datetime',
        'is_imprest' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    // Status constants
    const STATUS_DRAFT = 'Draft';
    const STATUS_SUBMITTED = 'Submitted';
    const STATUS_IN_APPROVAL = 'In_Approval';
    const STATUS_APPROVED = 'Approved';
    const STATUS_FINANCE = 'Finance';
    const STATUS_FINANCE_APPROVED = 'Finance_Approved';
    const STATUS_FINANCE_REJECTED = 'Finance_Rejected';
    const STATUS_PAYMENT = 'Payment';
    const STATUS_REJECTED = 'Rejected';
    const STATUS_REVERTED = 'Reverted';
    const STATUS_CLOSED = 'Closed';
    const STATUS_FINALIZED = 'Finalized';

    // Reverted from status constants
    const REVERTED_FROM_SUBMITTED = 'Submitted';
    const REVERTED_FROM_IN_APPROVAL = 'In_Approval';
    const REVERTED_FROM_APPROVED = 'Approved';
    const REVERTED_FROM_FINANCE = 'Finance';

    /**
     * Get all employees for this request
     */
    public function employees()
    {
        return $this->hasMany(AdvanceRequestEmployee::class, 'advance_request_id');
    }

    /**
     * Get all activities through employees
     */
    public function activities()
    {
        return $this->hasManyThrough(
            AdvanceRequestActivity::class,
            AdvanceRequestEmployee::class,
            'advance_request_id',
            'advance_request_employee_id',
            'id',
            'id'
        );
    }

    /**
     * Get all approvals through activities
     */
    public function approvals()
    {
        return $this->hasManyThrough(
            AdvanceActivityApproval::class,
            AdvanceRequestActivity::class,
            'advance_request_employee_id',
            'advance_request_activity_id',
            'id',
            'id'
        )->join('advance_request_employees', 'advance_request_activities.advance_request_employee_id', '=', 'advance_request_employees.id')
         ->where('advance_request_employees.advance_request_id', $this->id ?? 0);
    }

    /**
     * Get all actions for this request
     */
    public function actions()
    {
        return $this->hasMany(AdvanceRequestAction::class, 'advance_request_id');
    }

    /**
     * Get current submission approvals
     */
    public function currentApprovals()
    {
        return $this->approvals()->where('advance_activity_approvals.submission_no', $this->submission_no);
    }

    /**
     * Get pending approvals
     */
    public function pendingApprovals()
    {
        return $this->currentApprovals()->where('advance_activity_approvals.approver_status', 'Pending');
    }

    /**
     * Get approved approvals for current submission
     */
    public function approvedApprovals()
    {
        return $this->currentApprovals()->where('advance_activity_approvals.approver_status', 'Approved');
    }

    /**
     * Get total amount from all activities
     */
    public function getTotalAmountAttribute()
    {
        return $this->activities()->sum('amount');
    }

    /**
     * Get total approved amount from activities
     */
    public function getTotalApprovedAmountAttribute()
    {
        return $this->activities()
            ->where('approval_status', 'Approved')
            ->sum('amount');
    }

    /**
     * Get total finance approved amount
     */
    public function getTotalFinanceApprovedAmountAttribute()
    {
        return $this->activities()
            ->where('finance_status', 'Approved')
            ->sum('finance_approved_amount');
    }

    /**
     * Check if request can be submitted
     */
    public function canBeSubmitted()
    {
        return in_array($this->current_status, [self::STATUS_DRAFT, self::STATUS_REVERTED]);
    }

    /**
     * Check if request can be resubmitted
     */
    public function canBeResubmitted()
    {
        return $this->current_status === self::STATUS_REVERTED;
    }

    /**
     * Check if request is at finance level
     */
    public function isAtFinanceLevel()
    {
        return $this->current_status === self::STATUS_FINANCE;
    }

    /**
     * Get activity name relationship
     */
    public function activityName()
    {
        return $this->belongsTo(ActivityName::class, 'activity_name_id');
    }

    /**
     * Get advance type relationship
     */
    public function advanceType()
    {
        return $this->belongsTo(AdvanceRequestType::class, 'advance_type_id');
    }

    /**
     * Scope to only imprest advances
     */
    public function scopeImprest($query)
    {
        return $query->where('is_imprest', 1);
    }

    /**
     * Scope to exclude imprest advances (regular advances only)
     */
    public function scopeNotImprest($query)
    {
        return $query->where(function ($q) {
            $q->where('is_imprest', 0)->orWhereNull('is_imprest');
        });
    }

    /**
     * Activity log configuration
     */
    public function getActivitylogOptions(): \Spatie\Activitylog\LogOptions
    {
        return \Spatie\Activitylog\LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => "Advance Request {$eventName}")
            ->useLogName('advance_request')
            ->logAll();
    }
}