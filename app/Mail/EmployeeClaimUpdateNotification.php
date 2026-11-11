<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Tracker #29: employee-facing claim update (deduction, month status, payment).
 * Queued: needs a queue worker (php artisan queue:work) and the jobs table.
 */
class EmployeeClaimUpdateNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $kind  deduction | month_approved | month_financed | payment_confirmed
     * @param  array  $data  employeeName, monthName, lines (deductions), amount, date, utr, remark
     */
    public function __construct(public string $kind, public array $data)
    {
    }

    /**
     * UAT E-11: MAIL_ACTIVE and XEASY_EMPLOYEE_NOTIFICATIONS are checked again when the queue
     * worker sends the mail, so switching mail off also stops mails already queued.
     * (A worker caches config at boot: after changing .env run config:clear / config:cache
     * and queue:restart.)
     */
    public static function sendingAllowed(): bool
    {
        return filter_var(config('xeasy_notifications.mail_active', false), FILTER_VALIDATE_BOOLEAN)
            && filter_var(config('xeasy_notifications.employee.enabled', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function send($mailer)
    {
        if (! self::sendingAllowed()) {
            Log::info('EmployeeClaimUpdateNotification dropped at send time: MAIL_ACTIVE or XEASY_EMPLOYEE_NOTIFICATIONS is off.', ['kind' => $this->kind]);

            return null;
        }

        return parent::send($mailer);
    }

    public function build()
    {
        $subject = match ($this->kind) {
            'deduction' => 'Xeasy: amount deducted on your expense claim',
            'month_approved' => 'Xeasy: your expense claims for '.($this->data['monthName'] ?? '').' are approved',
            'month_financed' => 'Xeasy: your expense claims for '.($this->data['monthName'] ?? '').' are cleared by Finance',
            'payment_confirmed' => 'Xeasy: payment made for your '.($this->data['monthName'] ?? '').' expense claims',
            default => 'Xeasy: expense claim update',
        };

        return $this->subject($subject)
            ->view('emails.employee-claim-update', ['kind' => $this->kind, 'data' => $this->data]);
    }
}
