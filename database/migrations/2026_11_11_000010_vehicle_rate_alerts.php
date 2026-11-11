<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VEH-RATE-ALERT-01 (HR, 11 Nov 2026): one open alert per active employee whose 2W / 4W rate is zero, and a log
 * of every mail sent to HR about it (employee, date and time, first or reminder, status).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vehicle_rate_alerts')) {
            Schema::create('vehicle_rate_alerts', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('company_id')->default(1);
                $t->unsignedInteger('employee_id');
                $t->string('emp_code', 20)->nullable();
                $t->string('employee_name', 150)->nullable();
                $t->string('department', 100)->nullable();
                $t->string('issue', 300)->nullable();
                $t->string('status', 12)->default('open'); // open | resolved
                $t->dateTime('first_detected_at')->nullable();
                $t->dateTime('last_sent_at')->nullable();
                $t->unsignedInteger('send_count')->default(0);
                $t->dateTime('resolved_at')->nullable();
                $t->timestamps();
                $t->index(['company_id', 'employee_id', 'status']);
            });
        }
        if (! Schema::hasTable('vehicle_rate_alert_mails')) {
            Schema::create('vehicle_rate_alert_mails', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('alert_id')->index();
                $t->unsignedInteger('company_id')->default(1);
                $t->unsignedInteger('employee_id')->index();
                $t->string('emp_code', 20)->nullable();
                $t->string('employee_name', 150)->nullable();
                $t->string('issue', 300)->nullable();
                $t->string('kind', 12); // first | reminder
                $t->string('to_list', 500)->nullable();
                $t->string('cc_list', 1000)->nullable();
                $t->string('status', 16); // sent | failed | no_recipient
                $t->string('error', 500)->nullable();
                $t->dateTime('sent_at');
                $t->index(['sent_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_rate_alert_mails');
        Schema::dropIfExists('vehicle_rate_alerts');
    }
};
