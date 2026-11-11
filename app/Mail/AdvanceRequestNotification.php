<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdvanceRequestNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $emailData;

    /**
     * Create a new message instance.
     */
    public function __construct($emailData)
    {
        $this->emailData = $emailData;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->getSubjectByActionType();

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $actionType = $this->emailData['action_type'] ?? 'notification';

        // Determine which template to use based on action type
        $template = match ($actionType) {
            'submission' => 'emails.advance-request-approver',
            'employee_submission' => 'emails.advance-request-employee',
            'finance_submission' => 'emails.advance-request-finance',
            'approval' => 'emails.advance-request-approval',
            'rejection' => 'emails.advance-request-rejection',
            'finance_approval' => 'emails.advance-request-finance-approval',
            'finance_rejection' => 'emails.advance-request-finance-rejection',
            default => 'emails.advance-request-notification',
        };

        return new Content(
            html: $template,
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Get email subject based on action type
     */
    private function getSubjectByActionType(): string
    {
        $actionType = $this->emailData['action_type'] ?? 'notification';
        $requestNo = $this->emailData['request_no'] ?? 'N/A';
        $activityName = $this->emailData['activity_name'] ?? 'N/A';
        $activityType = $this->emailData['activity_type'] ?? 'N/A';
        $monthYear = $this->emailData['month_year'] ?? 'N/A';

        switch ($actionType) {
            case 'submission':
                $advanceTypeCode = $this->emailData['advance_type_code'] ?? 'Act';
                if ($advanceTypeCode === 'Exp') {
                    // For Expense type, use simpler subject
                    return "Advance request for Expense {$monthYear}";
                } else {
                    // For Activity and Other types, use activity name and type
                    return "Advance request for '{$activityName}' ({$activityType}) {$monthYear}";
                }
            case 'employee_submission':
                $advanceTypeCode = $this->emailData['advance_type_code'] ?? 'Act';
                if ($advanceTypeCode === 'Exp') {
                    // For Expense type, use simpler subject
                    return "Advance request for Expense {$monthYear}";
                } else {
                    // For Activity and Other types, use activity name and type
                    return "Advance request for '{$activityName}' ({$activityType}) {$monthYear}";
                }
            case 'finance_submission':
                $employeeCode = $this->emailData['employee_code'] ?? 'N/A';
                $advanceTypeCode = $this->emailData['advance_type_code'] ?? 'Activity';
                return "Advance approval for ({$employeeCode}) against {$advanceTypeCode} {$monthYear}";
            case 'approval':
                return "Advance Request #{$requestNo} - Approved";
            case 'rejection':
                return "Advance Request #{$requestNo} - Rejected";
            case 'finance_approval':
                return "Advance Request #{$requestNo} - Finance Approved";
            case 'finance_rejection':
                return "Advance Request #{$requestNo} - Finance Rejected";
            default:
                return "Advance Request #{$requestNo} - Status Update";
        }
    }
}
