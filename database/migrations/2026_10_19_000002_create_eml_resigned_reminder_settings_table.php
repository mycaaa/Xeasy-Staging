<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('eml_resigned_reminder_settings')) {
            Schema::create('eml_resigned_reminder_settings', function (Blueprint $table) {
                $table->id();
                $table->boolean('is_active')->default(true)->comment('Master switch for cron automation');
                $table->unsignedBigInteger('email_template_id')->nullable();
                $table->string('send_time', 10)->default('09:00');
                $table->string('frequency', 30)->default('daily')->comment('daily, weekdays, weekly');
                $table->unsignedInteger('min_days_between_reminders')->default(3)->comment('Minimum days to wait before sending next reminder to same employee');
                $table->unsignedInteger('max_reminders_per_employee')->default(5)->comment('Max reminders per employee, 0 = unlimited');
                $table->text('target_companies')->nullable()->comment('JSON array of CompanyIds, null = all companies');
                $table->unsignedInteger('days_before_separation')->default(60)->comment('Max days before separation to start reminders (0 = ignore)');
                $table->string('query_email', 100)->default('query@vnrseeds.com');
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });

            // Insert initial default settings row
            DB::table('eml_resigned_reminder_settings')->insert([
                'id'                          => 1,
                'is_active'                   => true,
                'send_time'                   => '09:00',
                'frequency'                   => 'daily',
                'min_days_between_reminders'  => 3,
                'max_reminders_per_employee'  => 5,
                'target_companies'            => json_encode([1, 2, 3, 4, 11]),
                'days_before_separation'      => 60,
                'query_email'                 => 'query@vnrseeds.com',
                'created_at'                  => now(),
                'updated_at'                  => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eml_resigned_reminder_settings');
    }
};
