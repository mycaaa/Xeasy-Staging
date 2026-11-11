<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ExportReadyNotification extends Notification
{
    use Queueable;

    protected $downloadUrl;

    public function __construct($downloadUrl)
    {
        $this->downloadUrl = $downloadUrl;
    }

    public function via($notifiable)
    {
        return ['mail']; // Add other channels like 'database' if needed
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Your Export is Ready')
            ->line('Your expense claim export has been generated.')
            ->action('Download File', $this->downloadUrl)
            ->line('Thank you for using our application!');
    }
}