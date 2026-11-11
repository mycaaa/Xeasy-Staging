<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdvanceRequestAction extends Model
{
    protected $table = 'advance_request_actions';

    protected $guarded = ['id'];

    protected $casts = [
        'action_at' => 'datetime'
    ];

    // Action type constants
    const ACTION_DRAFT_SAVE = 'Draft_Save';
    const ACTION_SUBMIT = 'Submit';
    const ACTION_RESUBMIT = 'Resubmit';
    const ACTION_APPROVE = 'Approve';
    const ACTION_REJECT = 'Reject';
    const ACTION_REVERT = 'Revert';
    const ACTION_FINANCE_APPROVE = 'Finance_Approve';
    const ACTION_FINANCE_REJECT = 'Finance_Reject';
    const ACTION_FINANCE_REVERT = 'Finance_Revert';
    const ACTION_PAYMENT_RELEASE = 'Payment_Release';
    const ACTION_PAYMENT_RELEASED = 'Payment_Released';
    const ACTION_FOCUS_EXPORT = 'Focus_Export';
    const ACTION_FOCUS_IMPORT = 'Focus_Import';
    const ACTION_FINALIZE_AMOUNT = 'Finalize_Amount';
    const ACTION_DELETE = 'Delete';

    /**
     * Get the advance request
     */
    public function advanceRequest()
    {
        return $this->belongsTo(AdvanceRequest::class, 'advance_request_id');
    }

    /**
     * Get the email template used
     */
    public function emailTemplate()
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    /**
     * Get action by user
     */
    public function actionBy()
    {
        // This would connect to your users table
        // return $this->belongsTo(User::class, 'action_by');
    }

    /**
     * Get action to user
     */
    public function actionTo()
    {
        // This would connect to your users table
        // return $this->belongsTo(User::class, 'action_to');
    }

    /**
     * Get related employee
     */
    public function relatedEmployee()
    {
        return $this->belongsTo(AdvanceRequestEmployee::class, 'related_employee_id');
    }

    /**
     * Get related activity
     */
    public function relatedActivity()
    {
        return $this->belongsTo(AdvanceRequestActivity::class, 'related_activity_id');
    }

    /**
     * Scope for specific action type
     */
    public function scopeActionType($query, $type)
    {
        return $query->where('action_type', $type);
    }

    /**
     * Scope for specific submission
     */
    public function scopeSubmission($query, $submissionNo)
    {
        return $query->where('submission_no', $submissionNo);
    }
}