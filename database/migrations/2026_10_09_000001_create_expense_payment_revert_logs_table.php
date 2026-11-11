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
        if (!Schema::hasTable('expense_payment_revert_logs')) {
            Schema::create('expense_payment_revert_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('company_id')->default(1);
                $table->unsignedSmallInteger('year_id');
                $table->unsignedBigInteger('record_id')->comment('ID from monthexpensefinal');
                $table->unsignedInteger('employee_id');
                $table->unsignedTinyInteger('claim_month');
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('from_stage', 50);
                $table->string('to_stage', 50);
                $table->text('reason');
                $table->unsignedBigInteger('reverted_by');
                $table->string('reverted_by_name', 150)->nullable();
                $table->string('reverted_by_code', 50)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();

                $table->index(['employee_id', 'claim_month', 'year_id'], 'idx_revert_emp_month_year');
                $table->index('record_id', 'idx_revert_record_id');
                $table->index('reverted_by', 'idx_revert_by');
                $table->index('created_at', 'idx_revert_created_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_payment_revert_logs');
    }
};
