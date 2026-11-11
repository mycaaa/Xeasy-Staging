<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdvanceActivityApproval extends Model
{
    protected $table = 'advance_activity_approvals';

    protected $guarded = ['id'];

    protected $casts = [
        'approver_amount' => 'decimal:2',
        'approver_date' => 'date',
        'reverted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Status constants
    const STATUS_PENDING = 'Pending';
    const STATUS_APPROVED = 'Approved';
    const STATUS_REJECTED = 'Rejected';
    const STATUS_REVERTED = 'Reverted';

    /**
     * Get the activity this approval belongs to
     */
    public function activity()
    {
        return $this->belongsTo(AdvanceRequestActivity::class, 'advance_request_activity_id');
    }

    /**
     * Get the advance request through activity
     */
    public function advanceRequest()
    {
        return $this->hasOneThrough(
            AdvanceRequest::class,
            AdvanceRequestActivity::class,
            'id',
            'id',
            'advance_request_activity_id',
            'advance_request_employee_id'
        )->join('advance_request_employees', 'advance_request_activities.advance_request_employee_id', '=', 'advance_request_employees.id');
    }

    /**
     * Get approver user
     */
    public function approver()
    {
        // This would connect to your users table
        // return $this->belongsTo(User::class, 'approver_id');
    }

    /**
     * Get reverter user
     */
    public function reverter()
    {
        // This would connect to your users table
        // return $this->belongsTo(User::class, 'reverted_by');
    }

    /**
     * Check if approval is pending
     */
    public function isPending()
    {
        return $this->approver_status === self::STATUS_PENDING;
    }

    /**
     * Check if approval is approved
     */
    public function isApproved()
    {
        return $this->approver_status === self::STATUS_APPROVED;
    }

    /**
     * Check if approval is rejected
     */
    public function isRejected()
    {
        return $this->approver_status === self::STATUS_REJECTED;
    }

    /**
     * Check if approval is reverted
     */
    public function isReverted()
    {
        return $this->approver_status === self::STATUS_REVERTED;
    }

    /**
     * Scope for current submission
     */
    public function scopeCurrentSubmission($query, $submissionNo)
    {
        return $query->where('submission_no', $submissionNo);
    }

    /**
     * Scope for specific approval level
     */
    public function scopeLevel($query, $level)
    {
        return $query->where('approval_level', $level);
    }

    /**
     * Scope for specific status
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('approver_status', $status);
    }
}