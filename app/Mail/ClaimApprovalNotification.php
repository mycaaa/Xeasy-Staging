<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ClaimApprovalNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $employeeName;
    public $monthName;

    public function __construct($employeeName, $monthName)
    {
        $this->employeeName = $employeeName;
        $this->monthName = $monthName;
    }

    public function build()
    {
        return $this->subject("Xeasy: Pending for approval (Month - {$this->monthName}, Emp - {$this->employeeName})")
                    ->from('admin@vnrseeds.co.in', 'Admin (Xeasy)')
                    ->view('emails.claim-approval-notification');
    }
}