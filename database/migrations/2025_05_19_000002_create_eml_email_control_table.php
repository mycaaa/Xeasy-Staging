<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eml_email_control', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('employee_id')->index();
            $table->string('emp_code', 50)->nullable();
            $table->string('employee_name', 200)->nullable();
            $table->string('department', 150)->nullable();
            $table->string('email', 200)->nullable();
            $table->boolean('is_active')->default(true)->comment('Master switch: can receive emails');
            $table->boolean('receive_submission_reminder')->default(true);
            $table->boolean('receive_escalation')->default(true);
            $table->boolean('receive_receiving_pending')->default(true);
            $table->boolean('receive_payment_notification')->default(true);
            $table->boolean('receive_approval_notification')->default(true);
            $table->string('reason', 500)->nullable()->comment('Reason for disabling');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eml_email_control');
    }
};
