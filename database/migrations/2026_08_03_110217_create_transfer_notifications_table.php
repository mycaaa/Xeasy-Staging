<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-005 & REQ-006: Transfer Notification log table.
 * Prevents duplicate notifications for the same transfer event.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guarded: the table may already exist (created by 2026_11_05_000003 or by hand).
        if (Schema::connection('mysql')->hasTable('transfer_notifications')) {
            return;
        }
        Schema::connection('mysql')->create('transfer_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('notification_type', 50); // company_transfer, location_transfer, dept_transfer, deputation
            $table->date('transfer_date');
            $table->timestamp('notified_at')->useCurrent();
            $table->string('triggered_by', 100)->nullable(); // 'cron' or admin emp_code
            $table->json('details')->nullable(); // extra context stored as JSON
            $table->timestamps();

            $table->index(['employee_id', 'transfer_date', 'notification_type'], 'idx_emp_transfer_unique');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('transfer_notifications');
    }
};
