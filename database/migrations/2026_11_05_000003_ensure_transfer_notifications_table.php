<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracker #34: the transfer / deputation notification command de-duplicates
 * through transfer_notifications (TransferNotificationService: NOT EXISTS
 * subqueries and logNotification()), but the table was never created on the
 * server (no `migrations` table in `expense`, so 2026_08_03_110217 never ran).
 * Same structure as 2026_08_03_110217; creates the table only if it is missing.
 *
 *   php artisan migrate --path=database/migrations/2026_11_05_000003_ensure_transfer_notifications_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('mysql')->hasTable('transfer_notifications')) {
            return;
        }
        Schema::connection('mysql')->create('transfer_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('notification_type', 50); // company_transfer, location_transfer, dept_transfer, deputation
            $table->date('transfer_date');
            $table->timestamp('notified_at')->useCurrent();
            $table->string('triggered_by', 100)->nullable();
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'transfer_date', 'notification_type'], 'idx_emp_transfer_unique');
        });
    }

    public function down(): void
    {
        // Only drop an empty table: the rows are the record of notifications already sent.
        if (Schema::connection('mysql')->hasTable('transfer_notifications')
            && ! \Illuminate\Support\Facades\DB::connection('mysql')->table('transfer_notifications')->exists()) {
            Schema::connection('mysql')->drop('transfer_notifications');
        }
    }
};
