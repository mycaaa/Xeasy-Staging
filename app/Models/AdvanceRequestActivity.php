<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdvanceRequestActivity extends Model
{
    use SoftDeletes;

    protected $table = 'advance_request_activities';

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'finance_approved_amount' => 'decimal:2',
        'payment_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'activity_crops_id' => 'json',
        'activity_variety_id' => 'json',
        'finance_date' => 'date',
        'payment_date' => 'date',
        'first_export_date' => 'date',
        'last_export_date' => 'date',
        'is_imprest' => 'boolean',
        'fx_amount' => 'decimal:2',
        'fx_rate' => 'decimal:4',
        'fx_paid_rate' => 'decimal:4',
        'fx_paid_amount' => 'decimal:2',
        'is_exported' => 'boolean',
        'export_count' => 'integer',
        'finalized_at' => 'datetime',
        'focus_exported_at' => 'datetime',
        'focus_imported_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    protected $appends = ['fx_label'];

    /** FX-53: amount is always INR; for a foreign currency it follows fx_amount x fx_rate. */
    protected static function booted(): void
    {
        static::saving(function (self $a) {
            if (! \App\Services\Advance\ForeignCurrencyAdvance::ready()) {
                foreach (['currency', 'fx_amount', 'fx_rate', 'fx_rate_date', 'fx_paid_rate', 'fx_paid_amount'] as $c) {
                    unset($a->attributes[$c]);
                }

                return;
            }
            if ($a->isDirty(['currency', 'fx_amount', 'fx_rate']) || ($a->exists === false && isset($a->attributes['currency']))) {
                $n = \App\Services\Advance\ForeignCurrencyAdvance::normalize([
                    'currency' => $a->attributes['currency'] ?? 'INR', 'fx_amount' => $a->attributes['fx_amount'] ?? null,
                    'fx_rate' => $a->attributes['fx_rate'] ?? null, 'fx_rate_date' => $a->attributes['fx_rate_date'] ?? null,
                ]);
                foreach ($n as $k => $v) {
                    $a->attributes[$k] = $v;
                }
            }
        });
    }

    public function getFxLabelAttribute(): ?string
    {
        return \App\Services\Advance\ForeignCurrencyAdvance::label($this->attributes + ['currency' => null]);
    }

    // Approval status constants
    const APPROVAL_STATUS_PENDING = 'Pending';
    const APPROVAL_STATUS_APPROVED = 'Approved';
    const APPROVAL_STATUS_REJECTED = 'Rejected';
    const APPROVAL_STATUS_REVERTED = 'Reverted';

    // Finance status constants
    const FINANCE_STATUS_PENDING = 'Pending';
    const FINANCE_STATUS_APPROVED = 'Approved';
    const FINANCE_STATUS_REJECTED = 'Rejected';
    const FINANCE_STATUS_REVERTED = 'Reverted';

    // Payment status constants
    const PAYMENT_STATUS_PENDING = 'Pending';
    const PAYMENT_STATUS_READY = 'Ready for Payment';
    const PAYMENT_STATUS_RELEASED = 'Released';
    const PAYMENT_STATUS_PAID = 'Paid';
    const PAYMENT_STATUS_REJECTED = 'Rejected';
    const PAYMENT_STATUS_REVERTED = 'Reverted';

    // Final status constants
    const FINAL_STATUS_PENDING = 'Pending';
    const FINAL_STATUS_EXPORTED = 'Exported to Focus';
    const FINAL_STATUS_IMPORTED = 'Imported from Focus';
    const FINAL_STATUS_FINALIZED = 'Finalized';
    const FINAL_STATUS_REJECTED = 'Rejected';

    /**
     * Get the employee
     */
    public function employee()
    {
        return $this->belongsTo(AdvanceRequestEmployee::class, 'advance_request_employee_id');
    }

    /**
     * Get the advance request through employee
     */
    public function advanceRequest()
    {
        return $this->hasOneThrough(
            AdvanceRequest::class,
            AdvanceRequestEmployee::class,
            'id',
            'id',
            'advance_request_employee_id',
            'advance_request_id'
        );
    }

    /**
     * Get the activity type
     */
    public function activityType()
    {
        return $this->belongsTo(ActivityType::class, 'activity_type_id');
    }

    /**
     * Get all approvals for this activity
     */
    public function approvals()
    {
        return $this->hasMany(AdvanceActivityApproval::class, 'advance_request_activity_id');
    }

    /**
     * Get current submission approvals
     */
    public function currentApprovals()
    {
        $submissionNo = $this->employee->advanceRequest->submission_no ?? 1;
        return $this->approvals()->where('submission_no', $submissionNo);
    }

    /**
     * Get pending approvals for current level
     */
    public function pendingApprovals()
    {
        return $this->currentApprovals()
            ->where('approval_level', $this->current_approval_level)
            ->where('approver_status', 'Pending');
    }

    /**
     * Get approved approvals for current submission
     */
    public function approvedApprovals()
    {
        return $this->currentApprovals()->where('approver_status', 'Approved');
    }

    /**
     * Get crops (if activity_crops_id is JSON)
     */
    public function getCropsAttribute()
    {
        if ($this->activity_crops_id) {
            // Assuming you have a CoreCrop model
            return CoreCrop::whereIn('id', $this->activity_crops_id)->get();
        }
        return collect();
    }

    /**
     * Get varieties (if activity_variety_id is JSON)
     */
    public function getVarietiesAttribute()
    {
        if ($this->activity_variety_id) {
            // Assuming you have a CoreVariety model
            return CoreVariety::whereIn('id', $this->activity_variety_id)->get();
        }
        return collect();
    }

    /**
     * Check if activity is approved
     */
    public function isApproved()
    {
        return $this->approval_status === self::APPROVAL_STATUS_APPROVED;
    }

    /**
     * Check if activity is finance approved
     */
    public function isFinanceApproved()
    {
        return $this->finance_status === self::FINANCE_STATUS_APPROVED;
    }

    /**
     * Check if activity payment is completed
     */
    public function isPaymentCompleted()
    {
        return $this->payment_status === self::PAYMENT_STATUS_PAID;
    }

    /**
     * Check if activity is finalized
     */
    public function isFinalized()
    {
        return $this->final_status === self::FINAL_STATUS_FINALIZED;
    }

    /**
     * Check if activity is exported to Focus
     */
    public function isExportedToFocus()
    {
        return in_array($this->final_status, [
            self::FINAL_STATUS_EXPORTED,
            self::FINAL_STATUS_IMPORTED,
            self::FINAL_STATUS_FINALIZED
        ]);
    }

    /**
     * Check if activity has been exported for payment
     */
    public function isExported()
    {
        return $this->is_exported === true;
    }

    /**
     * Mark activity as exported
     */
    public function markAsExported($userId = null)
    {
        $now = now()->toDateString();
        
        $this->update([
            'is_exported' => true,
            'first_export_date' => $this->first_export_date ?? $now,
            'last_export_date' => $now,
            'export_count' => $this->export_count + 1,
            'exported_by' => $userId
        ]);
    }

    /**
     * Reset export status (for re-export if needed)
     */
    public function resetExportStatus()
    {
        $this->update([
            'is_exported' => false,
            'first_export_date' => null,
            'last_export_date' => null,
            'export_count' => 0,
            'exported_by' => null
        ]);
    }

    /**
     * Get finance user
     */
    public function financeUser()
    {
        // This would connect to your users table
        // return $this->belongsTo(User::class, 'finance_id');
    }

    /**
     * Get payment processor user
     */
    public function paymentProcessor()
    {
        // This would connect to your users table
        // return $this->belongsTo(User::class, 'payment_id');
    }

    /**
     * Get all export logs for this activity
     */
    public function exportLogs()
    {
        return $this->hasMany(AdvanceExportLog::class, 'activity_id');
    }

    /**
     * Get the latest export log
     */
    public function latestExportLog()
    {
        return $this->hasOne(AdvanceExportLog::class, 'activity_id')->latest('exported_at');
    }
}