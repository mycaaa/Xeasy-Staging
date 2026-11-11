<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('eml_resigned_reminders')) {
            Schema::create('eml_resigned_reminders', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('employee_id')->unique();
                $table->string('emp_code', 50)->nullable()->index();
                $table->string('employee_name', 191)->nullable();
                $table->string('email', 191)->nullable();
                $table->string('department_name', 150)->nullable();
                $table->date('date_of_resignation')->nullable();
                $table->date('date_of_separation')->nullable();
                $table->unsignedInteger('reminder_count')->default(0)->comment('Hit count through cron job');
                $table->unsignedInteger('total_sent_count')->default(0)->comment('Total times email sent (cron + api)');
                $table->timestamp('last_sent_at')->nullable();
                $table->string('last_trigger_type', 20)->nullable()->comment('cron or api');
                $table->string('last_status', 30)->default('pending')->comment('sent, failed, pending');
                $table->text('last_error')->nullable();
                $table->timestamps();

                $table->index(['reminder_count', 'last_sent_at'], 'idx_resigned_reminder_status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eml_resigned_reminders');
    }
};
