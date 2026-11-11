<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * EmailLogService
 *
 * Central service for logging every outgoing email to eml_send_log.
 * Call log() immediately before or after Mail::to()->send() to record everything.
 *
 * Usage:
 *   app(EmailLogService::class)->log([
 *       'notification_type'      => 'expense_submission',
 *       'subject'                => $template->subject,
 *       'recipient_email'        => $employee->email,
 *       'recipient_employee_id'  => $employee->EmployeeId,
 *       'recipient_emp_code'     => $employee->emp_code,
 *       'recipient_name'         => $employee->name,
 *       'template_id'            => $template->id,
 *       'body_html'              => $body,
 *       'is_sent'                => true,
 *   ]);
 */
class EmailLogService
{
    /**
     * Log an email send attempt to eml_send_log.
     *
     * @param array $data  Any subset of eml_send_log columns.
     * @return int          The inserted row ID.
     */
    public function log(array $data): int
    {
        try {
            $authUser  = Auth::user();
            $companyId = session('company_id') ?? null;
            $yearId    = session('year_id')    ?? null;
            $manual    = ! empty($data['manual']) && ! app()->runningInConsole();
            unset($data['manual']);

            $row = [
                // Template
                'template_id'           => $data['template_id']           ?? null,
                'template_name'         => $data['template_name']          ?? null,

                // Categorization
                'notification_type'     => $data['notification_type'],
                'mail_category'         => $data['mail_category']          ?? $this->inferCategory($data['notification_type'] ?? ''),
                'stage'                 => $data['stage']                  ?? null,

                // Content
                'subject'               => $data['subject']                ?? '(no subject)',
                // MAIL-01b: log the body as sent (with the standard closing line)
                'body_html'             => isset($data['body_html']) && \App\Support\ReminderFooter::applies($data['subject'] ?? null) ? \App\Support\ReminderFooter::html((string) $data['body_html']) : ($data['body_html'] ?? null),
                'body_text'             => $data['body_text']              ?? null,

                // Recipient
                'recipient_employee_id' => $data['recipient_employee_id']  ?? null,
                'recipient_emp_code'    => $data['recipient_emp_code']     ?? null,
                'recipient_name'        => $data['recipient_name']         ?? null,
                'recipient_email'       => $data['recipient_email']        ?? '',
                'cc_emails'             => $data['cc_emails']              ?? null,
                'bcc_emails'            => $data['bcc_emails']             ?? null,

                // Sender/trigger
                // MAILLOG-01 (tracker, 27 Oct): mails the application sends on its own (reminders,
                // notifications that follow a stage change, scheduled runs) are triggered by "System".
                // Only a mail a person sends by hand (caller passes 'manual' => true) shows that person.
                'sender_employee_id'    => $data['sender_employee_id']     ?? (($manual && $authUser) ? ($authUser->employee_id ?? null) : null),
                'sender_emp_code'       => $data['sender_emp_code']        ?? (($manual && $authUser) ? ($authUser->emp_code ?? null) : null),
                'sender_name'           => $data['sender_name']            ?? (($manual && $authUser) ? ($authUser->name ?? null) : 'System'),

                // Context
                'sent_for_month'        => $data['sent_for_month']         ?? null,
                'year_id'               => $data['year_id']                ?? $yearId,
                'company_id'            => $data['company_id']             ?? $companyId,

                // Reference
                'ref_type'              => $data['ref_type']               ?? null,
                'ref_id'                => $data['ref_id']                 ?? null,

                // Status
                'is_sent'               => $data['is_sent']                ?? false,
                'sent_at'               => isset($data['is_sent']) && $data['is_sent'] ? now() : null,
                'failed_at'             => $data['failed_at']              ?? null,
                'error_message'         => $data['error_message']          ?? null,

                'created_at'            => now(),
                'updated_at'            => now(),
            ];

            // VVNR-LOG-01: VVNR mails go to the VVNR database's mail log (what the VVNR log screen reads)
            if ((int) $row['company_id'] === 11) {
                try {
                    return DB::connection('expense_vvnr')->table('eml_send_log')->insertGetId($row);
                } catch (\Throwable $e) {
                    Log::warning('EmailLogService: VVNR mail log not writable, logged on VNR: '.$e->getMessage());
                }
            }

            return DB::table('eml_send_log')->insertGetId($row);

        } catch (\Throwable $e) {
            // Never let logging break the actual email flow
            Log::error('EmailLogService::log failed', [
                'error'             => $e->getMessage(),
                'notification_type' => $data['notification_type'] ?? null,
                'recipient'         => $data['recipient_email'] ?? null,
            ]);
            return 0;
        }
    }

    /**
     * Convenience wrapper: log a successful send.
     */
    public function logSent(array $data): int
    {
        return $this->log(array_merge($data, ['is_sent' => true, 'failed_at' => null, 'error_message' => null]));
    }

    /**
     * Convenience wrapper: log a failed send.
     */
    public function logFailed(array $data, string $errorMessage): int
    {
        return $this->log(array_merge($data, [
            'is_sent'       => false,
            'failed_at'     => now(),
            'error_message' => $errorMessage,
        ]));
    }

    /**
     * Derive a broad category from the notification_type string.
     */
    private function inferCategory(string $type): string
    {
        if (str_contains($type, 'advance')) return 'advance';
        if (str_contains($type, 'payment')) return 'payment';
        if (str_contains($type, 'approval') || str_contains($type, 'claim')) return 'expense';
        if (str_contains($type, 'submission') || str_contains($type, 'hardcopy') || str_contains($type, 'escalation')) return 'expense';
        return 'general';
    }
}
