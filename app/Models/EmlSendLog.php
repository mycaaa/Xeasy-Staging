<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmlSendLog extends Model
{
    protected $table = 'eml_send_log';

    protected $fillable = [
        'template_id', 'template_name',
        'notification_type', 'mail_category', 'stage',
        'subject', 'body_html', 'body_text',
        'recipient_employee_id', 'recipient_emp_code', 'recipient_name', 'recipient_email',
        'cc_emails', 'bcc_emails',
        'sender_employee_id', 'sender_emp_code', 'sender_name',
        'sent_for_month', 'year_id', 'company_id',
        'ref_type', 'ref_id',
        'is_sent', 'sent_at', 'failed_at', 'error_message',
    ];

    protected $casts = [
        'is_sent'    => 'boolean',
        'sent_at'    => 'datetime',
        'failed_at'  => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** Relationship back to the email template */
    public function template()
    {
        return $this->belongsTo(EmailTemplate::class, 'template_id');
    }

    /** Scope: only successfully sent emails */
    public function scopeSent($query)
    {
        return $query->where('is_sent', true);
    }

    /** Scope: only failed emails */
    public function scopeFailed($query)
    {
        return $query->where('is_sent', false)->whereNotNull('failed_at');
    }
}
