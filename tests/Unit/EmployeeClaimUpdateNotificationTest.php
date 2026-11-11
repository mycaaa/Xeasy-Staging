<?php

namespace Tests\Unit;

use App\Mail\EmployeeClaimUpdateNotification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** UAT E-11: a queued employee mail re-checks MAIL_ACTIVE and the feature flag when it is sent. */
class EmployeeClaimUpdateNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // dcblogdev/laravel-sent-emails stores every sent mail here (MessageSent listener).
        Schema::dropIfExists('sent_emails');
        Schema::create('sent_emails', function (Blueprint $table) {
            $table->id();
            $table->dateTime('date')->nullable();
            $table->string('from')->nullable();
            $table->text('to')->nullable();
            $table->text('cc')->nullable();
            $table->text('bcc')->nullable();
            $table->string('subject')->nullable();
            $table->longText('body')->nullable();
            $table->boolean('has_attachment')->default(false);
            $table->timestamps();
        });
    }

    private function mailable(): EmployeeClaimUpdateNotification
    {
        return (new EmployeeClaimUpdateNotification('month_approved', ['employeeName' => 'Test', 'monthName' => 'October']))
            ->to('employee@example.test');
    }

    public function test_queued_mail_is_dropped_when_mail_active_was_switched_off(): void
    {
        config(['xeasy_notifications.mail_active' => false, 'xeasy_notifications.employee.enabled' => true]);
        $this->assertNull($this->mailable()->send(app('mail.manager')));
        $this->assertCount(0, app('mail.manager')->mailer('array')->getSymfonyTransport()->messages());

        // The queue worker path (SendQueuedMailable::handle) goes through the same send().
        (new SendQueuedMailable($this->mailable()))->handle(app('mail.manager'));
        $this->assertCount(0, app('mail.manager')->mailer('array')->getSymfonyTransport()->messages());
        $this->assertSame(0, DB::table('sent_emails')->count());
    }

    public function test_queued_mail_is_dropped_when_feature_flag_is_off(): void
    {
        config(['xeasy_notifications.mail_active' => true, 'xeasy_notifications.employee.enabled' => false]);
        $this->assertNull($this->mailable()->send(app('mail.manager')));
    }

    public function test_mail_is_sent_when_both_switches_are_on(): void
    {
        config(['xeasy_notifications.mail_active' => true, 'xeasy_notifications.employee.enabled' => true]);
        $this->assertNotNull($this->mailable()->send(app('mail.manager')));
        $this->assertCount(1, app('mail.manager')->mailer('array')->getSymfonyTransport()->messages());
        // UAT E-08 premise: the package listener already writes exactly one sent_emails row per mail.
        $this->assertSame(1, DB::table('sent_emails')->count());
    }
}
