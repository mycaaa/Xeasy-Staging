<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add effective_date to claim_rating_rules
        Schema::table('claim_rating_rules', function (Blueprint $table) {
            $table->date('effective_date')->nullable()->after('sort_order');
        });

        // Rating calculation settings (schedule)
        Schema::create('claim_rating_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('company_id')->default(1);
            $table->unsignedTinyInteger('calculation_day')->default(10)->comment('Day of month when ratings are calculated');
            $table->boolean('auto_calculate')->default(false)->comment('Auto-calculate on schedule');
            $table->date('last_calculated_date')->nullable();
            $table->unsignedBigInteger('last_calculated_by')->nullable();
            $table->timestamps();

            $table->unique('company_id');
        });

        // Stored rating results (calculated monthly)
        Schema::create('claim_rating_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('company_id');
            $table->unsignedInteger('year_id');
            $table->unsignedTinyInteger('month')->comment('Claim month being rated');
            $table->unsignedBigInteger('employee_id')->comment('The employee whose claim is being processed');
            $table->string('emp_code', 20)->nullable();
            $table->string('employee_name', 150)->nullable();
            $table->string('department_name', 100)->nullable();
            $table->string('stage_key', 50);
            $table->string('stage_label', 100);
            $table->string('rated_role', 50)->comment('Role being rated: employee, mediator, courier, verifier, approver, finance');
            $table->unsignedBigInteger('rated_person_id')->nullable()->comment('EmployeeID of the person being rated (verifier/approver etc.)');
            $table->string('rated_person_name', 150)->nullable();
            $table->unsignedSmallInteger('days_taken');
            $table->unsignedTinyInteger('stars')->comment('1-5 star rating');
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->unsignedBigInteger('rule_id')->nullable()->comment('Which rule was applied');
            $table->date('calculated_date')->comment('When this rating was calculated');
            $table->unsignedBigInteger('calculated_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'year_id', 'month']);
            $table->index(['employee_id', 'year_id', 'month']);
            $table->index(['rated_role', 'company_id']);
            $table->index(['rated_person_id']);
            $table->index(['calculated_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_rating_results');
        Schema::dropIfExists('claim_rating_settings');
        Schema::table('claim_rating_rules', function (Blueprint $table) {
            $table->dropColumn('effective_date');
        });
    }
};
