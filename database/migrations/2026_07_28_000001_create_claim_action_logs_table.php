<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claim_action_logs', function (Blueprint $table) {
            $table->id();

            // Identifiers
            $table->unsignedInteger('company_id');
            $table->unsignedSmallInteger('year_id');
            $table->unsignedBigInteger('exp_id')->nullable()->comment('NULL for month-level actions');
            $table->unsignedInteger('employee_id')->comment('Claim owner (CrBy)');
            $table->unsignedTinyInteger('claim_month');

            // Action info
            $table->string('action', 20)->comment('return | submit | resubmit');
            $table->string('action_by_role', 5)->comment('V=Verifier A=Approver AD=Admin');
            $table->unsignedBigInteger('action_by')->comment('User ID who performed the action');
            $table->string('action_by_name', 100)->nullable();

            // Return detail
            $table->text('reason')->nullable()->comment('Return reason / remark');
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();

            // Counts (how many times this employee/month/exp was returned)
            $table->unsignedSmallInteger('return_count')->default(0)
                ->comment('Cumulative return count for this employee+month at time of action');

            $table->timestamp('action_at')->useCurrent();

            // Indexes for fast reporting
            $table->index(['employee_id', 'claim_month', 'year_id'], 'idx_emp_month_year');
            $table->index('exp_id');
            $table->index(['company_id', 'action']);
            $table->index('action_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_action_logs');
    }
};
